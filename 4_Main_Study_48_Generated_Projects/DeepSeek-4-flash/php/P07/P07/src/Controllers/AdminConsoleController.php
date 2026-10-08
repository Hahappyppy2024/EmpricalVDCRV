<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\SettingsRepository;
use CloudFS\Repositories\UserRepository;
use CloudFS\Services\AuditService;
use CloudFS\Services\QuotaService;
use CloudFS\Services\RealtimeService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class AdminConsoleController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private UserRepository $users,
        private QuotaService $quota,
        private SettingsRepository $settings,
        private PhpRenderer $view,
        private AuditService $audit,
        private RealtimeService $realtime
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        return $this->view->render($response, 'admin.php', ['current_user' => $user]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        if (($user['role'] ?? 'user') !== 'admin') {
            return $this->json($response, ['ok' => false, 'errors' => ['Admin privileges are required.']], 403);
        }
        $payload = [
            'ok' => true,
            'users' => $this->users->listAll(),
            'settings' => $this->settings->all(),
            'blocked_file_types' => $this->settings->blockedExtensions(),
            'quota' => $this->quota->listAll(),
            'stats' => [
                'total_users' => $this->users->count(),
                'total_files' => (int) $this->db->value('SELECT COUNT(*) FROM files'),
                'total_audit_events' => (int) $this->db->value('SELECT COUNT(*) FROM audit_events'),
                'total_shares' => (int) $this->db->value('SELECT COUNT(*) FROM shares'),
                'total_teams' => (int) $this->db->value('SELECT COUNT(*) FROM teams'),
            ],
        ];
        return $this->json($response, $payload);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $action = (string) ($data['action'] ?? '');
        switch ($action) {
            case 'update_policy':
                return $this->updatePolicy($request, $response);
            case 'add_blocked':
                return $this->addBlocked($request, $response);
            default:
                return $this->json($response, ['ok' => false, 'errors' => ['Unknown admin action.']], 422);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $targetId = (int) $args['id'];
        $target = $this->users->findById($targetId);
        if (!$target) {
            return $this->json($response, ['ok' => false, 'errors' => ['User not found.']], 404);
        }
        if (array_key_exists('role', $data)) {
            $role = (string) $data['role'];
            if (!in_array($role, ['user', 'admin'], true)) {
                return $this->json($response, ['ok' => false, 'errors' => ['Role must be user or admin.']], 422);
            }
            if ($targetId === (int) $user['id']) {
                return $this->json($response, ['ok' => false, 'errors' => ['You cannot change your own role.']], 422);
            }
            $this->db->run('UPDATE users SET role = ?, updated_at = datetime(\'now\') WHERE id = ?', [$role, $targetId]);
            $this->audit->log((int) $user['id'], 'admin.user.role_changed', 'user', (string) $targetId, ['role' => $role]);
        }
        if (array_key_exists('quota_bytes', $data)) {
            $quotaBytes = (int) $data['quota_bytes'];
            $soft = (int) ($data['soft_limit'] ?? $quotaBytes);
            $hard = (int) ($data['hard_limit'] ?? $quotaBytes);
            if ($quotaBytes <= 0 || $hard < $soft) {
                return $this->json($response, ['ok' => false, 'errors' => ['Quota values are invalid.']], 422);
            }
            $this->quota->updateQuota($targetId, $quotaBytes, $soft, $hard);
            $this->audit->log((int) $user['id'], 'admin.user.quota_updated', 'user', (string) $targetId, ['quota_bytes' => $quotaBytes]);
        }
        return $this->json($response, ['ok' => true, 'message' => 'User updated.']);
    }

    public function setUserQuota(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $targetId = (int) $args['id'];
        $target = $this->users->findById($targetId);
        if (!$target) {
            return $this->json($response, ['ok' => false, 'errors' => ['User not found.']], 404);
        }
        $quotaBytes = (int) ($data['quota_bytes'] ?? $target['quota_bytes']);
        if ($quotaBytes <= 0) {
            return $this->json($response, ['ok' => false, 'errors' => ['Quota must be a positive number of bytes.']], 422);
        }
        $this->quota->updateQuota($targetId, $quotaBytes, $quotaBytes, $quotaBytes);
        $this->audit->log((int) $user['id'], 'admin.user.quota_updated', 'user', (string) $targetId, ['quota_bytes' => $quotaBytes]);
        $this->realtime->publish('admin.quota.updated', ['user' => $target['username'], 'quota' => $quotaBytes]);
        return $this->json($response, ['ok' => true, 'message' => 'Quota updated.']);
    }

    public function updatePolicy(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $fields = ['default_quota_bytes', 'max_upload_bytes', 'trash_retention_days', 'version_retention_count', 'maintenance_mode', 'allow_registration'];
        $changed = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $this->settings->set($field, (string) $data[$field]);
                $changed[] = $field;
            }
        }
        if (!$changed) {
            return $this->json($response, ['ok' => false, 'errors' => ['No settings supplied.']], 422);
        }
        $this->audit->log((int) $user['id'], 'admin.policy.updated', 'policy', 'storage_policies', ['fields' => $changed]);
        $this->realtime->publish('admin.policy.updated', ['user' => $user['username'], 'fields' => $changed]);
        return $this->json($response, ['ok' => true, 'message' => 'Storage policies updated.']);
    }

    public function addBlocked(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $extension = (string) ($data['extension'] ?? '');
        $reason = (string) ($data['reason'] ?? '');
        $result = $this->settings->addBlockedExtension($extension, $reason, (int) $user['id']);
        if (!$result['ok']) {
            return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
        }
        $this->audit->log((int) $user['id'], 'admin.blocked_type.added', 'policy', 'blocked_file_types', ['extension' => $extension]);
        return $this->json($response, ['ok' => true, 'message' => 'Blocked file type added.'], 201);
    }

    public function removeBlocked(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $extension = (string) $args['extension'];
        $this->settings->removeBlockedExtension($extension);
        $this->audit->log((int) $user['id'], 'admin.blocked_type.removed', 'policy', 'blocked_file_types', ['extension' => $extension]);
        return $this->json($response, ['ok' => true, 'message' => 'Blocked file type removed.']);
    }
}
