<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditEventRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-12 — Audit logs and admin operations.
 */
final class AuditLogsController
{
    public function __construct(
        private SessionService $session,
        private AuditEventRepository $audit,
        private UserRepository $users,
        private AuditService $auditService,
        private PhpRenderer $view
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Admin only.'];
            return $response->withHeader('Location', $session ? '/dashboard' : '/login')->withStatus(302);
        }
        $query = $request->getQueryParams();
        $action = isset($query['action']) ? trim((string)$query['action']) : null;
        $actor = isset($query['actor']) ? trim((string)$query['actor']) : null;
        $events = $this->audit->all($action !== '' ? $action : null, $actor !== '' ? $actor : null, 200);
        $users = $this->users->all();
        return $this->view->render($response, 'audit_logs.php', [
            'title' => 'Audit logs and admin operations',
            'session' => $session,
            'events' => $events,
            'users' => $users,
            'filter_action' => $action,
            'filter_actor' => $actor,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    }

    public function manageUsers(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Admin only.'];
            return $response->withHeader('Location', $session ? '/dashboard' : '/login')->withStatus(302);
        }
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        $userId = (int)($data['user_id'] ?? 0);
        $user = $this->users->findById($userId);
        if (!$user) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown user.'];
            return $response->withHeader('Location', '/audit')->withStatus(302);
        }
        if ((int)$user['id'] === (int)$session['user_id'] && ($action === 'disable' || $action === 'delete')) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'You cannot disable or delete yourself.'];
            return $response->withHeader('Location', '/audit')->withStatus(302);
        }
        switch ($action) {
            case 'enable':
                $this->users->setEnabled($userId, true);
                break;
            case 'disable':
                $this->users->setEnabled($userId, false);
                break;
            case 'promote':
                $this->users->setRole($userId, 'admin');
                break;
            case 'demote':
                $this->users->setRole($userId, 'operator');
                break;
            case 'reset_password':
                $newPassword = 'Temp#' . substr(bin2hex(random_bytes(4)), 0, 8);
                $this->users->updatePassword($userId, $newPassword);
                $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Temporary password for ' . $user['username'] . ': ' . $newPassword];
                $this->auditService->recordFromSession($session, 'audit.user_password_reset', 'user', ['user_id' => $userId]);
                return $response->withHeader('Location', '/audit')->withStatus(302);
            case 'delete':
                $this->users->delete($userId);
                break;
            default:
                $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown action.'];
                return $response->withHeader('Location', '/audit')->withStatus(302);
        }
        $this->auditService->recordFromSession($session, 'audit.user_' . $action, 'user', [
            'user_id' => $userId,
            'username' => $user['username'],
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => ucfirst($action) . ' completed.'];
        return $response->withHeader('Location', '/audit')->withStatus(302);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $query = $request->getQueryParams();
        $events = $this->audit->all(
            isset($query['action']) ? (string)$query['action'] : null,
            isset($query['actor']) ? (string)$query['actor'] : null,
            200
        );
        $payload = json_encode(['events' => $events]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apiCreate(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        $userId = (int)($data['user_id'] ?? 0);
        $user = $this->users->findById($userId);
        if (!$action || !$user) {
            $payload = json_encode(['ok' => false, 'error' => 'action and user_id are required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $validActions = ['enable', 'disable', 'promote', 'demote'];
        if (!in_array($action, $validActions, true)) {
            $payload = json_encode(['ok' => false, 'error' => 'invalid action']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        if ($action === 'enable') {
            $this->users->setEnabled($userId, true);
        } elseif ($action === 'disable') {
            $this->users->setEnabled($userId, false);
        } elseif ($action === 'promote') {
            $this->users->setRole($userId, 'admin');
        } else {
            $this->users->setRole($userId, 'operator');
        }
        $this->auditService->recordFromSession($session, 'audit.user_' . $action . '_api', 'user', ['user_id' => $userId]);
        $payload = json_encode(['ok' => true, 'user' => $this->users->findById($userId)]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        $userId = (int)($data['user_id'] ?? 0);
        $user = $this->users->findById($userId);
        if (!$action || !$user) {
            $payload = json_encode(['ok' => false, 'error' => 'action and user_id are required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        if ($action !== 'reset_password') {
            $payload = json_encode(['ok' => false, 'error' => 'unsupported action']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $newPassword = 'Temp#' . substr(bin2hex(random_bytes(4)), 0, 8);
        $this->users->updatePassword($userId, $newPassword);
        $this->auditService->recordFromSession($session, 'audit.user_password_reset_api', 'user', ['user_id' => $userId]);
        $payload = json_encode(['ok' => true, 'temp_password' => $newPassword]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}