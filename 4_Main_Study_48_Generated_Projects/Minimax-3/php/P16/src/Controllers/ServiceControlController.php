<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ServiceRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-04 — Service control.
 */
final class ServiceControlController
{
    public function __construct(
        private SessionService $session,
        private ServiceRepository $services,
        private AuditService $audit,
        private PhpRenderer $view
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $services = $this->services->all();
        return $this->view->render($response, 'service_control.php', [
            'title' => 'Service control',
            'session' => $session,
            'services' => $services,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    }

    public function act(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        $svc = $this->services->findById($id);
        if (!$svc) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown service.'];
            return $response->withHeader('Location', '/services')->withStatus(302);
        }
        $allowed = ['start', 'stop', 'restart', 'inspect'];
        if (!in_array($action, $allowed, true)) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Invalid action.'];
            return $response->withHeader('Location', '/services')->withStatus(302);
        }
        if ($action !== 'inspect' && $session['role'] !== 'admin') {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Only admins can perform control operations.'];
            return $response->withHeader('Location', '/services')->withStatus(302);
        }
        $current = $svc['state'];
        $newState = match (true) {
            $action === 'start' && $current === 'stopped' => 'running',
            $action === 'stop'  && $current === 'running' => 'stopped',
            $action === 'restart' && in_array($current, ['running', 'failed'], true) => 'running',
            default => null,
        };
        if ($action === 'inspect') {
            $newState = $current; // no-op, just records
        } elseif ($newState === null) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Invalid state transition from ' . $current . '.'];
            return $response->withHeader('Location', '/services')->withStatus(302);
        }
        if ($newState !== null) {
            $this->services->transition($id, $newState, (int)$session['user_id'], $action);
        }
        $this->audit->recordFromSession($session, 'service_control.action', 'service', [
            'service_id' => $id,
            'service_name' => $svc['name'],
            'action' => $action,
            'state' => $newState ?? $current,
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => ucfirst($action) . ' executed on ' . $svc['name'] . '.'];
        return $response->withHeader('Location', '/services')->withStatus(302);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $payload = json_encode(['services' => $this->services->all()]);
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
        $name = trim((string)($data['name'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        if ($name === '') {
            $payload = json_encode(['ok' => false, 'error' => 'name is required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        if ($this->services->findByName($name)) {
            $payload = json_encode(['ok' => false, 'error' => 'Service already exists.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
        $id = $this->services->create($name, $description, 'stopped');
        $this->audit->recordFromSession($session, 'service_control.create_api', 'service', ['service_id' => $id]);
        $payload = json_encode(['ok' => true, 'id' => $id]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $id = (int)($args['id'] ?? 0);
        $svc = $this->services->findById($id);
        if (!$svc) {
            $payload = json_encode(['ok' => false, 'error' => 'Unknown service.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        $allowed = ['start', 'stop', 'restart'];
        if (!in_array($action, $allowed, true)) {
            $payload = json_encode(['ok' => false, 'error' => 'Invalid action.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $current = $svc['state'];
        $newState = match (true) {
            $action === 'start' && $current === 'stopped' => 'running',
            $action === 'stop'  && $current === 'running' => 'stopped',
            $action === 'restart' && in_array($current, ['running', 'failed'], true) => 'running',
            default => null,
        };
        if ($newState === null) {
            $payload = json_encode(['ok' => false, 'error' => 'Invalid transition from ' . $current]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $this->services->transition($id, $newState, (int)$session['user_id'], $action);
        $this->audit->recordFromSession($session, 'service_control.update_api', 'service', [
            'service_id' => $id,
            'action' => $action,
        ]);
        $payload = json_encode(['ok' => true, 'state' => $newState]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}