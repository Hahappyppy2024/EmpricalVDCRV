<?php

declare(strict_types=1);

namespace App\Controller;

use App\Middleware\SessionMiddleware;
use App\Service\AuthService;
use App\Support\Http;
use App\Support\Validation;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController extends BaseController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function showLogin(Request $request, ResponseInterface $response): ResponseInterface
    {
        if (Http::isAuth($request)) {
            return $this->redirect($response, '/');
        }

        return $this->render($request, $response, 'auth.php', [
            'pageTitle' => 'Sign in',
            'mode' => 'login',
        ]);
    }

    public function login(Request $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $identifier = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        [$error, $token] = $this->auth->login($identifier, $password);
        if ($error !== null) {
            return $this->render($request, $response, 'auth.php', [
                'pageTitle' => 'Sign in',
                'mode' => 'login',
                'error' => $error,
                'old' => ['username' => $identifier],
            ]);
        }

        return $this->withSessionCookie($response, $token)->withHeader('Location', '/')->withStatus(302);
    }

    public function showRegister(Request $request, ResponseInterface $response): ResponseInterface
    {
        if (Http::isAuth($request)) {
            return $this->redirect($response, '/');
        }

        return $this->render($request, $response, 'auth.php', [
            'pageTitle' => 'Create account',
            'mode' => 'register',
        ]);
    }

    public function register(Request $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $username = trim((string) ($body['username'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $passwordConfirm = (string) ($body['password_confirm'] ?? '');
        $fullName = trim((string) ($body['full_name'] ?? ''));

        $errors = [];
        if ($username === '') {
            $errors[] = 'Username is required.';
        }
        if (!Validation::email($email)) {
            $errors[] = 'A valid email address is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($errors !== []) {
            return $this->render($request, $response, 'auth.php', [
                'pageTitle' => 'Create account',
                'mode' => 'register',
                'error' => $errors[0],
                'old' => ['username' => $username, 'email' => $email, 'full_name' => $fullName],
            ]);
        }

        [$error, $token] = $this->auth->register($username, $email, $password, $fullName);
        if ($error !== null) {
            return $this->render($request, $response, 'auth.php', [
                'pageTitle' => 'Create account',
                'mode' => 'register',
                'error' => $error,
                'old' => ['username' => $username, 'email' => $email, 'full_name' => $fullName],
            ]);
        }

        return $this->withSessionCookie($response, $token)->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, ResponseInterface $response): ResponseInterface
    {
        $user = Http::user($request);
        $session = Http::session($request);
        if ($user !== null && $session !== null) {
            $this->auth->logout($session['id'], (int) $user['id'], $user['username']);
        }

        $expired = sprintf('%s=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0', SessionMiddleware::COOKIE);

        return $response->withAddedHeader('Set-Cookie', $expired)->withHeader('Location', '/login')->withStatus(302);
    }

    private function withSessionCookie(ResponseInterface $response, string $token): ResponseInterface
    {
        $cookie = sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Lax; Max-Age=%d',
            SessionMiddleware::COOKIE,
            $token,
            86400
        );

        return $response->withAddedHeader('Set-Cookie', $cookie);
    }
}
