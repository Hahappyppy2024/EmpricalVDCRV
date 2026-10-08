<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class AccountAccessController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private PhpRenderer $view,
        private AuditService $audit
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'account_access.php', ['current_user' => $request->getAttribute('current_user')]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $rows = $this->db->all(
            'SELECT id, user_id, username, action, status, ip_address, created_at
             FROM account_access
             WHERE user_id = ? OR (user_id IS NULL AND username = ?)
             ORDER BY created_at DESC LIMIT 100',
            [(int) $user['id'], $user['username']]
        );
        return $this->json($response, ['ok' => true, 'records' => $rows]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $action = (string) ($data['action'] ?? '');
        $allowed = ['register', 'login', 'login_failed', 'logout', 'reset_request', 'reset'];
        if (!in_array($action, $allowed, true)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Unknown account action.']], 422);
        }
        if ($action !== 'logout' && $action !== 'reset_request') {
            return $this->json($response, ['ok' => false, 'errors' => ['This action is recorded automatically during authentication.']], 422);
        }
        $status = (string) ($data['status'] ?? 'success');
        $id = $this->db->insert(
            'INSERT INTO account_access (user_id, username, action, status, ip_address, created_at) VALUES (?, ?, ?, ?, ?, datetime(\'now\'))',
            [(int) $user['id'], $user['username'], $action, $status === 'failure' ? 'failure' : 'success', $request->getServerParams()['REMOTE_ADDR'] ?? null]
        );
        $this->audit->log((int) $user['id'], 'account_access.created', 'account_access', (string) $id, ['action' => $action]);
        return $this->json($response, ['ok' => true, 'id' => $id, 'message' => 'Account access event recorded.']);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $id = (int) $args['id'];
        $row = $this->db->one('SELECT * FROM account_access WHERE id = ?', [$id]);
        if (!$row || ((int) $row['user_id'] !== (int) $user['id'])) {
            return $this->json($response, ['ok' => false, 'errors' => ['Record not found or out of scope.']], 404);
        }
        $data = $this->parseBody($request);
        $status = (string) ($data['status'] ?? $row['status']);
        $this->db->run('UPDATE account_access SET status = ? WHERE id = ?', [$status === 'failure' ? 'failure' : 'success', $id]);
        $this->audit->log((int) $user['id'], 'account_access.updated', 'account_access', (string) $id, ['status' => $status]);
        return $this->json($response, ['ok' => true, 'id' => $id, 'message' => 'Account access event updated.']);
    }
}
