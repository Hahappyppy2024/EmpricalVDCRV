<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AccountAccessService;
use App\Service\AuthService;
use App\Support\Http;
use App\Support\Validation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AccountController extends BaseController
{
    private AccountAccessService $service;

    private AuthService $auth;

    public function __construct()
    {
        $this->service = new AccountAccessService();
        $this->auth = new AuthService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireAuth($request, $response);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'account.php', [
            'pageTitle' => 'Account',
            'activeNav' => 'account',
            'profile' => $this->service->profile((int) $user['id']),
            'accessLog' => $this->service->accessLog((int) $user['id']),
        ]);
    }

    public function update(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireAuth($request, $response);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $fullName = trim((string) ($body['full_name'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));

        if (!Validation::email($email)) {
            $this->flash($request, 'error', 'A valid email address is required.');
        } else {
            [$error] = $this->service->updateProfile((int) $user['id'], $fullName, $email);
            $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Profile updated.');
        }

        return $this->redirect($response, '/account');
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireAuth($request, $response);
        if ($denied !== null) {
            return $this->error($response, 'Authentication required.', 401);
        }
        $user = $this->user($request);

        return $this->json($response, [
            'success' => true,
            'account' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
            ],
            'access_log' => $this->service->accessLog((int) $user['id']),
        ]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];

        if (!empty($body['email']) && !empty($body['username']) && !empty($body['password'])) {
            $username = trim((string) $body['username']);
            $email = trim((string) $body['email']);
            $password = (string) $body['password'];
            $fullName = trim((string) ($body['full_name'] ?? ''));
            if (!Validation::email($email)) {
                return $this->error($response, 'A valid email address is required.', 422);
            }
            if (strlen($password) < 8) {
                return $this->error($response, 'Password must be at least 8 characters.', 422);
            }
            [$error, $token] = $this->auth->register($username, $email, $password, $fullName);
        } else {
            $identifier = trim((string) ($body['username'] ?? ''));
            $password = (string) ($body['password'] ?? '');
            if ($identifier === '' || $password === '') {
                return $this->error($response, 'Username and password are required.', 422);
            }
            [$error, $token] = $this->auth->login($identifier, $password);
        }

        if ($error !== null) {
            return $this->error($response, $error, 401);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Account access granted.',
            'session_token' => $token,
            'confirmation' => 'Account access granted.',
        ], 201);
    }

    public function apiUpdate(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireAuth($request, $response);
        if ($denied !== null) {
            return $this->error($response, 'Authentication required.', 401);
        }
        $user = $this->user($request);
        if ((int) $user['id'] !== (int) ($args['id'] ?? 0)) {
            return $this->error($response, 'You can only update your own account.', 403);
        }

        $body = $request->getParsedBody() ?? [];
        $fullName = trim((string) ($body['full_name'] ?? $user['full_name']));
        $email = trim((string) ($body['email'] ?? $user['email']));
        if (!Validation::email($email)) {
            return $this->error($response, 'A valid email address is required.', 422);
        }

        [$error, $updated] = $this->service->updateProfile((int) $user['id'], $fullName, $email);
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Account updated.',
            'record' => $updated,
        ]);
    }
}
