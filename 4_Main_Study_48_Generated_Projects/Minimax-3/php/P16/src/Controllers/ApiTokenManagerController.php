<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ApiTokenRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-11 — API token manager.
 */
final class ApiTokenManagerController
{
    public function __construct(
        private SessionService $session,
        private ApiTokenRepository $tokens,
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
        $tokens = $this->tokens->all($session['role'] === 'admin' ? null : (int)$session['user_id']);
        return $this->view->render($response, 'api_token_manager.php', [
            'title' => 'API token manager',
            'session' => $session,
            'tokens' => $tokens,
            'flash' => $_SESSION['flash'] ?? null,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $data = (array)$request->getParsedBody();
        $name = trim((string)($data['name'] ?? ''));
        $scopes = trim((string)($data['scopes'] ?? 'read'));
        $expiresIn = (int)($data['expires_in_days'] ?? 30);
        if ($name === '') {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Name is required.'];
            return $response->withHeader('Location', '/api_tokens')->withStatus(302);
        }
        $raw = bin2hex(random_bytes(20));
        $prefix = 'p16_' . substr($raw, 0, 6);
        $token = $prefix . '_' . substr($raw, 6);
        $hash = hash('sha256', $token);
        $expiresAt = $expiresIn > 0
            ? (new \DateTimeImmutable('+' . $expiresIn . ' days'))->format('Y-m-d H:i:s')
            : null;
        $id = $this->tokens->create((int)$session['user_id'], $name, $hash, $prefix, $scopes, $expiresAt);
        $this->audit->recordFromSession($session, 'api_token_manager.create', 'api_token', [
            'token_id' => $id,
            'prefix' => $prefix,
        ]);
        $_SESSION['flash'] = [
            'kind' => 'success',
            'msg' => 'Token created. Copy the value now (it is shown once): ' . $token,
        ];
        return $response->withHeader('Location', '/api_tokens')->withStatus(302);
    }

    public function revoke(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $id = (int)$args['id'];
        if (!$this->tokens->revoke($id, $session['role'] === 'admin' ? null : (int)$session['user_id'])) {
            $_SESSION['flash'] = ['kind' => 'error', 'msg' => 'Unable to revoke.'];
        } else {
            $this->audit->recordFromSession($session, 'api_token_manager.revoke', 'api_token', ['token_id' => $id]);
            $_SESSION['flash'] = ['kind' => 'success', 'msg' => 'Token revoked.'];
        }
        return $response->withHeader('Location', '/api_tokens')->withStatus(302);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            $payload = json_encode(['error' => 'forbidden']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        $payload = json_encode(['tokens' => $this->tokens->all($session['role'] === 'admin' ? null : (int)$session['user_id'])]);
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
        if ($name === '') {
            $payload = json_encode(['ok' => false, 'error' => 'name is required.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $raw = bin2hex(random_bytes(20));
        $prefix = 'p16_' . substr($raw, 0, 6);
        $token = $prefix . '_' . substr($raw, 6);
        $hash = hash('sha256', $token);
        $expiresIn = (int)($data['expires_in_days'] ?? 30);
        $expiresAt = $expiresIn > 0
            ? (new \DateTimeImmutable('+' . $expiresIn . ' days'))->format('Y-m-d H:i:s')
            : null;
        $id = $this->tokens->create(
            (int)$session['user_id'],
            $name,
            $hash,
            $prefix,
            (string)($data['scopes'] ?? 'read'),
            $expiresAt
        );
        $this->audit->recordFromSession($session, 'api_token_manager.create_api', 'api_token', ['token_id' => $id]);
        $payload = json_encode([
            'ok' => true,
            'id' => $id,
            'token' => $token,
            'message' => 'Save the token now. It is only displayed once.',
        ]);
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
        $data = (array)$request->getParsedBody();
        $action = (string)($data['action'] ?? '');
        if ($action !== 'revoke') {
            $payload = json_encode(['ok' => false, 'error' => 'invalid action']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        if (!$this->tokens->revoke($id, $session['role'] === 'admin' ? null : (int)$session['user_id'])) {
            $payload = json_encode(['ok' => false, 'error' => 'Unable to revoke.']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        $this->audit->recordFromSession($session, 'api_token_manager.revoke_api', 'api_token', ['token_id' => $id]);
        $payload = json_encode(['ok' => true]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}