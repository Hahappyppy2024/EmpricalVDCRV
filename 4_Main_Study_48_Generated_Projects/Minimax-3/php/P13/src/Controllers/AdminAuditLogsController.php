<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\AuditRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class AdminAuditLogsController
{
    public function __construct(
        private AuditRepository $audit,
        private SessionService $session,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'system_admin') {
            return $this->view->render($response->withStatus(403), 'audit.php', [
                'user' => $user, 'items' => [], 'filters' => [],
                'flash' => ['ok' => false, 'msg' => 'Only system admins can read audit logs.'],
            ]);
        }
        $params = $request->getQueryParams();
        $filters = [
            'action' => isset($params['action']) ? trim((string) $params['action']) : null,
            'actor_role' => isset($params['actor_role']) ? trim((string) $params['actor_role']) : null,
            'target_type' => isset($params['target_type']) ? trim((string) $params['target_type']) : null,
            'since' => isset($params['since']) ? trim((string) $params['since']) : null,
        ];
        $items = $this->audit->search(array_filter($filters));
        return $this->view->render($response, 'audit.php', [
            'user' => $user,
            'items' => $items,
            'filters' => $filters,
            'flash' => null,
        ]);
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'system_admin') {
            return ResponseHelper::stableError($response, 'forbidden_role', 403);
        }
        $params = $request->getQueryParams();
        $filters = [
            'action' => isset($params['action']) ? trim((string) $params['action']) : null,
            'actor_role' => isset($params['actor_role']) ? trim((string) $params['actor_role']) : null,
            'target_type' => isset($params['target_type']) ? trim((string) $params['target_type']) : null,
            'since' => isset($params['since']) ? trim((string) $params['since']) : null,
        ];
        $items = $this->audit->search(array_filter($filters));
        if ($items === []) {
            return ResponseHelper::json($response, ['items' => [], 'bounded_empty' => true, 'filters' => $filters]);
        }
        return ResponseHelper::json($response, ['items' => $items, 'filters' => $filters]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'system_admin') {
            return ResponseHelper::stableError($response, 'forbidden_role', 403);
        }
        $data = (array) $request->getParsedBody();
        $action = trim((string) ($data['action'] ?? ''));
        $targetType = trim((string) ($data['target_type'] ?? null));
        $targetId = trim((string) ($data['target_id'] ?? null));
        $detail = trim((string) ($data['detail'] ?? null));
        if ($action === '') {
            return ResponseHelper::validationError($response, ['action' => 'required']);
        }
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], $action, $targetType, $targetId, $detail, $ip);
        return ResponseHelper::json($response, ['ok' => true], 201);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user['role'] !== 'system_admin') {
            return ResponseHelper::stableError($response, 'forbidden_role', 403);
        }
        $id = (int) ($args['id'] ?? 0);
        $data = (array) $request->getParsedBody();
        $action = trim((string) ($data['action'] ?? ''));
        $detail = trim((string) ($data['detail'] ?? null));
        if ($action === '') {
            return ResponseHelper::validationError($response, ['action' => 'required']);
        }
        $pdo = \MailServer\Database\Database::connection();
        $pdo->prepare('UPDATE audit_events SET action = ?, detail = COALESCE(detail, "") || ? WHERE id = ?')->execute([$action, ' [amended]', $id]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'audit.amend', 'audit', (string) $id, $detail, $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}