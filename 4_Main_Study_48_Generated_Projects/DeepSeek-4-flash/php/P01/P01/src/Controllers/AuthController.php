<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Http;
use App\Repositories\SystemRepository;
use App\Services\AuthService;
use App\Services\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * LMS-01 Account access: registration, sign-in, recovery, sign-out.
 */
final class AuthController
{
    public function __construct(
        private Config $config,
        private AuthService $auth,
        private Validator $validator,
        private SystemRepository $system,
    ) {
    }

    public function loginForm(Request $request, Response $response): Response
    {
        if ($request->getAttribute('user') !== null) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        return Http::view($response, $this->config, 'auth/login', ['error' => '']);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $errors = $this->validator->required($data, ['identifier', 'password']);
        if ($errors !== []) {
            return Http::view($response, $this->config, 'auth/login', ['error' => 'Username/email and password are required']);
        }
        $result = $this->auth->attemptLogin((string) $data['identifier'], (string) $data['password'], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1'));
        if (isset($result['error'])) {
            return Http::view($response, $this->config, 'auth/login', ['error' => $result['error']]);
        }
        return $response->withHeader('Location', '/dashboard')->withStatus(302)
            ->withAddedHeader('Set-Cookie', AuthService::cookieHeader($result['token'], $this->config->sessionLifetimeMinutes()));
    }

    public function registerForm(Request $request, Response $response): Response
    {
        if ($request->getAttribute('user') !== null) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        return Http::view($response, $this->config, 'auth/register', ['error' => '']);
    }

    public function register(Request $request, Response $response): Response
    {
        if (!$this->config->allowRegistration()) {
            return Http::view($response, $this->config, 'auth/register', ['error' => 'Registration is currently disabled by an administrator']);
        }
        $data = (array) ($request->getParsedBody() ?? []);
        $errors = $this->validator->validate($data, [
            'username' => ['required', 'min:3'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
            'display_name' => ['required', 'min:2'],
        ]);
        if ($errors !== []) {
            return Http::view($response, $this->config, 'auth/register', ['error' => reset($errors)]);
        }
        $result = $this->auth->register((string) $data['username'], (string) $data['email'], (string) $data['password'], (string) $data['display_name'], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1'));
        if (isset($result['error'])) {
            return Http::view($response, $this->config, 'auth/register', ['error' => $result['error']]);
        }
        return $response->withHeader('Location', '/dashboard')->withStatus(302)
            ->withAddedHeader('Set-Cookie', AuthService::cookieHeader($result['token'], $this->config->sessionLifetimeMinutes()));
    }

    public function forgotForm(Request $request, Response $response): Response
    {
        return Http::view($response, $this->config, 'auth/forgot', ['error' => '', 'notice' => '']);
    }

    public function forgot(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $identifier = trim((string) ($data['identifier'] ?? ''));
        if ($identifier === '') {
            return Http::view($response, $this->config, 'auth/forgot', ['error' => 'Enter your username or email', 'notice' => '']);
        }
        $result = $this->auth->createPasswordReset($identifier);
        if (isset($result['error'])) {
            return Http::view($response, $this->config, 'auth/forgot', ['error' => $result['error'], 'notice' => '']);
        }
        $resetUrl = $this->config->baseUrl() . '/reset-password?token=' . $result['token'];
        return Http::view($response, $this->config, 'auth/forgot', [
            'error' => '',
            'notice' => 'If the account exists, a reset link has been generated. (Local demo token: <a href="' . htmlspecialchars($resetUrl) . '">' . htmlspecialchars($resetUrl) . '</a>)',
        ]);
    }

    public function resetForm(Request $request, Response $response): Response
    {
        $token = (string) ($request->getQueryParams()['token'] ?? '');
        if ($token === '') {
            return $response->withHeader('Location', '/forgot-password')->withStatus(302);
        }
        return Http::view($response, $this->config, 'auth/reset', ['error' => '', 'token' => $token]);
    }

    public function reset(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if (strlen($password) < 8) {
            return Http::view($response, $this->config, 'auth/reset', ['error' => 'The new password must be at least 8 characters', 'token' => $token]);
        }
        $result = $this->auth->resetPassword($token, $password);
        if (isset($result['error'])) {
            return Http::view($response, $this->config, 'auth/reset', ['error' => $result['error'], 'token' => $token]);
        }
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $token = AuthService::tokenFromRequest($request);
        $user = $request->getAttribute('user');
        if ($token !== null) {
            $this->auth->destroyToken($token);
        }
        if ($user !== null) {
            $this->auth->logAccess((int) $user['id'], 'logout');
        }
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
