<?php

declare(strict_types=1);

namespace App\Controller;

use App\AuthService;
use App\Middleware\AuthMiddleware;
use App\SessionService;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly SessionService $sessions,
        private readonly View $view
    ) {
    }

    public function loginPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->currentUser($request) !== null) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $response->getBody()->write($this->view->render('auth/login', [
            'title' => 'Sign in',
            'flash' => $request->getQueryParams()['flash'] ?? '',
        ], ''));

        return $response;
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];
        $username = (string) ($data['username'] ?? '');
        $password = (string) ($data['password'] ?? '');

        try {
            $result = $this->auth->login($username, $password, $this->clientIp($request));
        } catch (\App\ValidationException $e) {
            return $this->redirectWithFlash($response, '/login', 'Invalid username or password.');
        }

        return $this->openSession($request, $response, $result['token']);
    }

    public function registerPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->currentUser($request) !== null) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $response->getBody()->write($this->view->render('auth/register', [
            'title' => 'Create account',
            'flash' => $request->getQueryParams()['flash'] ?? '',
        ], ''));

        return $response;
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];
        try {
            $result = $this->auth->register($data, $this->clientIp($request));
        } catch (\App\ValidationException $e) {
            return $this->redirectWithFlash($response, '/register', 'Registration failed: ' . $e->getMessage());
        }

        return $this->openSession($request, $response, $result['token']);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user') ?? [];
        $token = $request->getCookieParams()[$this->sessions->cookieName()] ?? '';
        $this->auth->logout($token, $user);

        $response = $response->withHeader('Set-Cookie', $this->sessions->cookieName() . '=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0');

        return $response->withStatus(302)->withHeader('Location', '/login');
    }

    private function openSession(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {
        $cookie = $this->sessions->cookieName() . '=' . $token
            . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=' . $this->sessions->lifetimeSeconds();

        return $response->withHeader('Set-Cookie', $cookie)->withStatus(302)->withHeader('Location', '/');
    }

    private function currentUser(ServerRequestInterface $request): ?array
    {
        $token = $request->getCookieParams()[$this->sessions->cookieName()] ?? '';

        return $this->sessions->currentUser($token);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        return $request->getServerParams()['REMOTE_ADDR'] ?? '';
    }

    private function redirectWithFlash(ResponseInterface $response, string $location, string $flash): ResponseInterface
    {
        return $response->withStatus(302)->withHeader('Location', $location . '?flash=' . rawurlencode($flash));
    }
}
