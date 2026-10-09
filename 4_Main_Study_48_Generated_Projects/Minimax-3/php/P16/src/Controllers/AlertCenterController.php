<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AlertRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-09 — Alert center.
 */
final class AlertCenterController
{
    public function __construct(
        private SessionService $session,
        private AlertRepository $alerts,
        private UserRepository $users,
        private AuditService $audit,
        private PhpRenderer $view
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $query = $request->getQueryParams();
        $severity = isset($query['severity']) ? (string)$query['severity'] : null;
        $state = isset($query['state']) ? (string)$query['state'] : null;
        $alerts = $this->alerts->all($severity ?: null, $state ?: null);
        $operators = $this->users->operators();
        return $this->view->render($response, 'alert_center.php', [
            'title' => 'Alert center',
            'session' => $session,
            'alerts' => $alerts,
            'operators' => $operators,
            'severity' => $severity,
            'state' => $state,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $data = (array)$request->getParsedBody();
        $title = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));
        $severity = (string)($data['severity'] ?? 'info');
        $source = (string)($data['source'] ?? 'manual');
        if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
            $severity = 'info';
        }
        if ($title === '') {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Title is required.'];
            return $response->withHeader('Location', '/alerts')->withStatus(302);
        }
        $id = $this->alerts->create($severity, $title, $message, $source);
        $this->audit->recordFromSession($session, 'alert_center.create', 'alert', ['alert_id' => $id]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Alert created.'];
        return $response->withHeader('Location', '/alerts')->withStatus(302);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $alert = $this->alerts->findById($id);
        if (!$alert) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown alert.'];
            return $response->withHeader('Location', '/alerts')->withStatus(302);
        }
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        $fields = [];
        switch ($action) {
            case 'acknowledge':
                $fields = [
                    'state' => 'acknowledged',
                    'acknowledged_by' => (int)$session['user_id'],
                    'acknowledged_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ];
                break;
            case 'assign':
                $assignee = (int)($data['assignee_id'] ?? 0);
                if ($assignee <= 0) {
                    $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Pick a valid assignee.'];
                    return $response->withHeader('Location', '/alerts')->withStatus(302);
                }
                $fields = ['state' => 'assigned', 'assignee_id' => $assignee];
                break;
            case 'comment':
                $body = trim((string)($data['body'] ?? ''));
                if ($body !== '') {
                    $this->alerts->addComment($id, (int)$session['user_id'], $body);
                    $this->audit->recordFromSession($session, 'alert_center.comment', 'alert', ['alert_id' => $id]);
                }
                $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Comment added.'];
                return $response->withHeader('Location', '/alerts')->withStatus(302);
            case 'resolve':
                $fields = [
                    'state' => 'resolved',
                    'resolved_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ];
                break;
            case 'close':
                $fields = ['state' => 'closed'];
                break;
            default:
                $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown action.'];
                return $response->withHeader('Location', '/alerts')->withStatus(302);
        }
        $this->alerts->updateState($id, $fields);
        $this->audit->recordFromSession($session, 'alert_center.update', 'alert', [
            'alert_id' => $id,
            'action' => $action,
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Alert updated.'];
        return $response->withHeader('Location', '/alerts')->withStatus(302);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $query = $request->getQueryParams();
        $alerts = $this->alerts->all(
            isset($query['severity']) ? (string)$query['severity'] : null,
            isset($query['state']) ? (string)$query['state'] : null
        );
        $payload = json_encode(['alerts' => $alerts]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apiCreate(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $data = (array)$request->getParsedBody();
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $payload = json_encode(['ok' => false, 'error' => 'title is required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $severity = (string)($data['severity'] ?? 'info');
        if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
            $payload = json_encode(['ok' => false, 'error' => 'invalid severity']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $id = $this->alerts->create($severity, $title, (string)($data['message'] ?? ''), (string)($data['source'] ?? 'api'));
        $this->audit->recordFromSession($session, 'alert_center.create_api', 'alert', ['alert_id' => $id]);
        $payload = json_encode(['ok' => true, 'id' => $id]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $id = (int)($args['id'] ?? 0);
        $alert = $this->alerts->findById($id);
        if (!$alert) {
            $payload = json_encode(['ok' => false, 'error' => 'unknown alert']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        $fields = match ($action) {
            'acknowledge' => [
                'state' => 'acknowledged',
                'acknowledged_by' => (int)$session['user_id'],
                'acknowledged_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ],
            'resolve' => [
                'state' => 'resolved',
                'resolved_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ],
            'assign' => [
                'state' => 'assigned',
                'assignee_id' => (int)($data['assignee_id'] ?? 0) ?: null,
            ],
            'close' => ['state' => 'closed'],
            default => null,
        };
        if ($fields === null) {
            $payload = json_encode(['ok' => false, 'error' => 'invalid action']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $this->alerts->updateState($id, $fields);
        $this->audit->recordFromSession($session, 'alert_center.update_api', 'alert', ['alert_id' => $id, 'action' => $action]);
        $payload = json_encode(['ok' => true, 'alert' => $this->alerts->findById($id)]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}