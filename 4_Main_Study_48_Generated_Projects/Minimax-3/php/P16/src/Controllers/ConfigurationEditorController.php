<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ConfigurationRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-08 — Configuration editor.
 */
final class ConfigurationEditorController
{
    public function __construct(
        private SessionService $session,
        private ConfigurationRepository $config,
        private AuditService $audit,
        private PhpRenderer $view
    ) {}

    public function showPage(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Admin only.'];
            return $response->withHeader('Location', $session ? '/dashboard' : '/login')->withStatus(302);
        }
        $rows = $this->config->all();
        return $this->view->render($response, 'configuration_editor.php', [
            'title' => 'Configuration editor',
            'session' => $session,
            'rows' => $rows,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    }

    public function upsert(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $data = (array)$request->getParsedBody();
        $key = trim((string)($data['key'] ?? ''));
        $value = (string)($data['value'] ?? '');
        $category = trim((string)($data['category'] ?? 'general')) ?: 'general';
        $description = trim((string)($data['description'] ?? ''));
        if ($key === '') {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Key is required.'];
            return $response->withHeader('Location', '/configuration')->withStatus(302);
        }
        $this->config->upsert($key, $value, $category, $description, (int)$session['user_id']);
        $this->audit->recordFromSession($session, 'configuration_editor.upsert', 'configuration_key', [
            'key' => $key,
            'value_preview' => substr($value, 0, 60),
        ]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Configuration saved.'];
        return $response->withHeader('Location', '/configuration')->withStatus(302);
    }

    public function stage(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $pending = (string)($data['pending_value'] ?? '');
        if ($this->config->findById($id) === null) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unknown key.'];
            return $response->withHeader('Location', '/configuration')->withStatus(302);
        }
        $this->config->stagePending($id, $pending, (int)$session['user_id']);
        $this->audit->recordFromSession($session, 'configuration_editor.stage', 'configuration_key', ['id' => $id]);
        $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Pending change staged.'];
        return $response->withHeader('Location', '/configuration')->withStatus(302);
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        if (!$this->config->approvePending($id)) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Nothing to approve.'];
        } else {
            $this->audit->recordFromSession($session, 'configuration_editor.approve', 'configuration_key', ['id' => $id]);
            $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Change approved.'];
        }
        return $response->withHeader('Location', '/configuration')->withStatus(302);
    }

    public function reject(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        if (!$this->config->rejectPending($id)) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Nothing to reject.'];
        } else {
            $this->audit->recordFromSession($session, 'configuration_editor.reject', 'configuration_key', ['id' => $id]);
            $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Change rejected.'];
        }
        return $response->withHeader('Location', '/configuration')->withStatus(302);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $payload = json_encode(['keys' => $this->config->all()]);
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
        $key = trim((string)($data['key'] ?? ''));
        if ($key === '') {
            $payload = json_encode(['ok' => false, 'error' => 'key is required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $this->config->upsert(
            $key,
            (string)($data['value'] ?? ''),
            (string)($data['category'] ?? 'general'),
            (string)($data['description'] ?? ''),
            (int)$session['user_id']
        );
        $this->audit->recordFromSession($session, 'configuration_editor.upsert_api', 'configuration_key', ['key' => $key]);
        $payload = json_encode(['ok' => true, 'key' => $this->config->findByKey($key)]);
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
        $id = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        if (!in_array($action, ['approve', 'reject', 'stage'], true)) {
            $payload = json_encode(['ok' => false, 'error' => 'Invalid action.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        if ($action === 'approve') {
            $ok = $this->config->approvePending($id);
        } elseif ($action === 'reject') {
            $ok = $this->config->rejectPending($id);
        } else {
            $pending = (string)($data['pending_value'] ?? '');
            if ($pending === '' || $this->config->findById($id) === null) {
                $ok = false;
            } else {
                $this->config->stagePending($id, $pending, (int)$session['user_id']);
                $ok = true;
            }
        }
        if (!$ok) {
            $payload = json_encode(['ok' => false, 'error' => 'Nothing to do.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $this->audit->recordFromSession($session, 'configuration_editor.' . $action . '_api', 'configuration_key', ['id' => $id]);
        $payload = json_encode(['ok' => true]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}