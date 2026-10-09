<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\HealthCheckRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-10 — Health check targets.
 */
final class HealthCheckTargetsController
{
    public function __construct(
        private SessionService $session,
        private HealthCheckRepository $health,
        private AuditService $audit,
        private PhpRenderer $view
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $targets = $this->health->all($session['role'] === 'admin' ? null : (int)$session['user_id']);
        return $this->view->render($response, 'health_check_targets.php', [
            'title' => 'Health check targets',
            'session' => $session,
            'targets' => $targets,
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
        $name = trim((string)($data['name'] ?? ''));
        $kind = (string)($data['kind'] ?? 'http');
        $target = trim((string)($data['target'] ?? ''));
        $interval = max(10, (int)($data['interval_sec'] ?? 60));
        $timeout = max(100, (int)($data['timeout_ms'] ?? 2000));
        if ($name === '' || $target === '' || !in_array($kind, ['http', 'tcp', 'script'], true)) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Name, kind and target are required.'];
            return $response->withHeader('Location', '/health_targets')->withStatus(302);
        }
        $id = $this->health->create([
            'owner_id' => (int)$session['user_id'],
            'name' => $name,
            'kind' => $kind,
            'target' => $target,
            'interval_sec' => $interval,
            'timeout_ms' => $timeout,
        ]);
        $this->audit->recordFromSession($session, 'health_check_targets.create', 'health_check_target', ['target_id' => $id]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Target saved.'];
        return $response->withHeader('Location', '/health_targets')->withStatus(302);
    }

    public function check(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $target = $this->health->findById($id);
        if (!$target) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown target.'];
            return $response->withHeader('Location', '/health_targets')->withStatus(302);
        }
        if ($session['role'] !== 'admin' && (int)$target['owner_id'] !== (int)$session['user_id']) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'You may not run this check.'];
            return $response->withHeader('Location', '/health_targets')->withStatus(302);
        }
        $state = $this->runLocalCheck($target);
        $this->health->recordCheck($id, $state, '');
        $this->audit->recordFromSession($session, 'health_check_targets.check', 'health_check_target', [
            'target_id' => $id,
            'state' => $state,
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Check executed. State: ' . $state];
        return $response->withHeader('Location', '/health_targets')->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $target = $this->health->findById($id);
        if (!$target) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown target.'];
            return $response->withHeader('Location', '/health_targets')->withStatus(302);
        }
        if ($session['role'] !== 'admin' && (int)$target['owner_id'] !== (int)$session['user_id']) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'You may not delete this target.'];
            return $response->withHeader('Location', '/health_targets')->withStatus(302);
        }
        $this->health->delete($id, $session['role'] === 'admin' ? null : (int)$session['user_id']);
        $this->audit->recordFromSession($session, 'health_check_targets.delete', 'health_check_target', ['target_id' => $id]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Target deleted.'];
        return $response->withHeader('Location', '/health_targets')->withStatus(302);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            $payload = json_encode(['error' => 'auth_required']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        $payload = json_encode(['targets' => $this->health->all($session['role'] === 'admin' ? null : (int)$session['user_id'])]);
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
        $name = trim((string)($data['name'] ?? ''));
        $kind = (string)($data['kind'] ?? 'http');
        $target = trim((string)($data['target'] ?? ''));
        if ($name === '' || $target === '' || !in_array($kind, ['http', 'tcp', 'script'], true)) {
            $payload = json_encode(['ok' => false, 'error' => 'name, kind and target are required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $id = $this->health->create([
            'owner_id' => (int)$session['user_id'],
            'name' => $name,
            'kind' => $kind,
            'target' => $target,
            'interval_sec' => max(10, (int)($data['interval_sec'] ?? 60)),
            'timeout_ms' => max(100, (int)($data['timeout_ms'] ?? 2000)),
        ]);
        $this->audit->recordFromSession($session, 'health_check_targets.create_api', 'health_check_target', ['target_id' => $id]);
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
        $target = $this->health->findById($id);
        if (!$target) {
            $payload = json_encode(['ok' => false, 'error' => 'unknown target']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        if ($session['role'] !== 'admin' && (int)$target['owner_id'] !== (int)$session['user_id']) {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? 'update');
        if ($action === 'check') {
            $state = $this->runLocalCheck($target);
            $this->health->recordCheck($id, $state, '');
            $payload = json_encode(['ok' => true, 'state' => $state]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
        $this->health->update($id, [
            'name' => (string)($data['name'] ?? $target['name']),
            'kind' => (string)($data['kind'] ?? $target['kind']),
            'target' => (string)($data['target'] ?? $target['target']),
            'interval_sec' => max(10, (int)($data['interval_sec'] ?? $target['interval_sec'])),
            'timeout_ms' => max(100, (int)($data['timeout_ms'] ?? $target['timeout_ms'])),
        ]);
        $this->audit->recordFromSession($session, 'health_check_targets.update_api', 'health_check_target', ['target_id' => $id]);
        $payload = json_encode(['ok' => true, 'target' => $this->health->findById($id)]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function runLocalCheck(array $target): string
    {
        if ($target['kind'] === 'script') {
            return 'healthy';
        }
        if ($target['kind'] === 'tcp') {
            $parts = explode(':', (string)$target['target'], 2);
            $host = $parts[0] ?: '127.0.0.1';
            $port = isset($parts[1]) ? (int)$parts[1] : 80;
            if ($port <= 0 || $port > 65535) {
                return 'down';
            }
            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client(
                'tcp://' . $host . ':' . $port,
                $errno,
                $errstr,
                max(1, (int)($target['timeout_ms'] / 1000))
            );
            if ($socket === false) {
                return 'down';
            }
            fclose($socket);
            return 'healthy';
        }
        $url = (string)$target['target'];
        if (!preg_match('#^https?://#i', $url)) {
            return 'down';
        }
        return 'healthy';
    }
}