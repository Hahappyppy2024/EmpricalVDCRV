<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use App\Services\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * SYS-01 — Account access.
 */
final class AccountAccessController
{
    public function __construct(
        private SessionService $session,
        private UserRepository $users,
        private AuditService $audit,
        private PhpRenderer $view
    ) {}

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'login.php', [
            'title' => 'Account access',
            'session' => null,
            'error' => null,
            'identifier' => '',
            'flash' => $_SESSION['flash'] ?? null,
        ])->withHeader('Cache-Control', 'no-store');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $identifier = trim((string)($data['identifier'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return $this->renderLogin($response, 'Identifier and password are required.', $identifier, 400);
        }

        $user = $this->users->findByUsername($identifier) ?? $this->users->findByEmail($identifier);
        if (!$user || (int)$user['enabled'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
            $this->audit->record(null, $identifier, 'account_access.login_failed', 'session', ['identifier' => $identifier]);
            return $this->renderLogin($response, 'Invalid credentials.', $identifier, 401);
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->session->login((int)$user['id'], $ip, $ua);
        $this->audit->record((int)$user['id'], (string)$user['username'], 'account_access.login_success', 'session', [
            'role' => $user['role'],
        ], $ip);

        $target = $user['role'] === 'admin' ? '/admin' : '/dashboard';
        return $response->withHeader('Location', $target)->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $session = $this->session->start();
        if ($session) {
            $this->audit->recordFromSession($session, 'account_access.logout', 'session');
        }
        $this->session->logout();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    public function register(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $errors = Validator::required($data, [
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'Password',
            'full_name' => 'Full name',
        ]);
        if (!empty($errors)) {
            return $this->renderRegister($response, $this->implodeErrors($errors), $data, 422);
        }
        try {
            $username = Validator::string($data, 'username', 80);
            $email = Validator::email($data, 'email');
            $password = (string)($data['password'] ?? '');
            $fullName = Validator::string($data, 'full_name', 120);
            if (!$username || !$email || !$fullName) {
                return $this->renderRegister($response, 'All fields are required.', $data, 422);
            }
            if (strlen($password) < 8) {
                return $this->renderRegister($response, 'Password must be at least 8 characters.', $data, 422);
            }
            if ($this->users->findByUsername($username)) {
                return $this->renderRegister($response, 'Username already taken.', $data, 409);
            }
            if ($this->users->findByEmail($email)) {
                return $this->renderRegister($response, 'Email already registered.', $data, 409);
            }
            $id = $this->users->create($username, $email, $password, 'operator', $fullName);
            $this->audit->record($id, $username, 'account_access.registered', 'user', ['email' => $email]);
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\InvalidArgumentException $e) {
            return $this->renderRegister($response, $e->getMessage(), $data, 422);
        }
    }

    public function showRegister(Request $request, Response $response): Response
    {
        return $this->renderRegister($response, null, [], 200);
    }

    public function apiIndex(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        $payload = [
            'endpoint' => 'account_access',
            'auth_required' => true,
            'session' => $session,
            'users' => $session && $session['role'] === 'admin' ? $this->users->all() : [],
        ];
        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apiCreate(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session) {
            return $this->jsonError($response, 'Authentication required.', 401);
        }
        $data = (array)$request->getParsedBody();
        try {
            $errors = Validator::required($data, ['username' => 'Username', 'email' => 'Email', 'password' => 'Password']);
            if (!empty($errors)) {
                return $this->jsonError($response, $this->implodeErrors($errors), 422);
            }
            $id = $this->users->create(
                Validator::string($data, 'username', 80) ?? '',
                Validator::email($data, 'email') ?? '',
                (string)($data['password'] ?? ''),
                Validator::enum($data + ['role' => 'operator'], 'role', ['operator', 'admin']) ?? 'operator',
                Validator::string($data, 'full_name', 120) ?? ''
            );
            $this->audit->recordFromSession($session, 'account_access.user_create_api', 'user', ['user_id' => $id]);
            $body = json_encode(['ok' => true, 'id' => $id]);
            $response->getBody()->write($body);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Throwable $e) {
            return $this->jsonError($response, $e->getMessage(), 422);
        }
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $session = $this->session->start();
        if (!$session || $session['role'] !== 'admin') {
            return $this->jsonError($response, 'Forbidden.', 403);
        }
        $id = (int)($args['id'] ?? 0);
        $user = $this->users->findById($id);
        if (!$user) {
            return $this->jsonError($response, 'User not found.', 404);
        }
        $data = (array)$request->getParsedBody();
        if (isset($data['role'])) {
            try {
                $role = Validator::enum($data, 'role', ['operator', 'admin']);
                if ($role) {
                    $this->users->setRole($id, $role);
                }
            } catch (\InvalidArgumentException $e) {
                return $this->jsonError($response, $e->getMessage(), 422);
            }
        }
        if (array_key_exists('enabled', $data)) {
            $this->users->setEnabled($id, (bool)$data['enabled']);
        }
        if (!empty($data['password'])) {
            $this->users->updatePassword($id, (string)$data['password']);
        }
        $this->audit->recordFromSession($session, 'account_access.user_update_api', 'user', ['target_user_id' => $id]);
        $payload = json_encode(['ok' => true, 'user' => $this->users->findById($id)]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function renderLogin(Response $response, ?string $error, string $identifier, int $status): Response
    {
        return $this->view->render($response, 'login.php', [
            'title' => 'Account access',
            'error' => $error,
            'identifier' => $identifier,
            'session' => null,
            'flash' => null,
        ])->withStatus($status);
    }

    private function renderRegister(Response $response, ?string $error, array $data, int $status): Response
    {
        return $this->view->render($response, 'register.php', [
            'title' => 'Create account',
            'error' => $error,
            'session' => null,
            'data' => $data,
        ])->withStatus($status);
    }

    private function jsonError(Response $response, string $message, int $status): Response
    {
        $payload = json_encode(['ok' => false, 'error' => $message]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function implodeErrors(array $errors): string
    {
        return implode(' ', array_map(fn($k, $v) => $v, array_keys($errors), $errors));
    }
}