<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Services\AuthService;
use CloudFS\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class AuthController
{
    public function __construct(
        private AuthService $auth,
        private SessionService $sessions,
        private PhpRenderer $view,
        private array $config
    ) {
    }

    public function registerForm(Request $request, Response $response): Response
    {
        return $this->renderGuest($response, 'auth/register.php', ['error' => null, 'form' => []]);
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $result = $this->auth->register(
            trim((string) ($data['username'] ?? '')),
            trim((string) ($data['email'] ?? '')),
            (string) ($data['password'] ?? ''),
            trim((string) ($data['full_name'] ?? '')),
            $request->getServerParams()['REMOTE_ADDR'] ?? null
        );
        if (!$result['ok']) {
            return $this->renderGuest($response, 'auth/register.php', [
                'error' => $result['errors'][0] ?? 'Registration failed.',
                'form' => $data,
            ]);
        }
        return $this->withSessionCookie($response, $result['session_token'])
            ->withHeader('Location', '/dashboard')
            ->withStatus(303);
    }

    public function loginForm(Request $request, Response $response): Response
    {
        return $this->renderGuest($response, 'auth/login.php', ['error' => null, 'form' => []]);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $result = $this->auth->login(
            trim((string) ($data['identifier'] ?? '')),
            (string) ($data['password'] ?? ''),
            $request->getServerParams()['REMOTE_ADDR'] ?? null
        );
        if (!$result['ok']) {
            return $this->renderGuest($response, 'auth/login.php', [
                'error' => $result['errors'][0] ?? 'Sign-in failed.',
                'form' => $data,
            ]);
        }
        return $this->withSessionCookie($response, $result['session_token'])
            ->withHeader('Location', '/dashboard')
            ->withStatus(303);
    }

    public function logout(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $cookies = $request->getCookieParams();
        $token = $cookies[$this->sessions->cookieName()] ?? null;
        if ($user && $token) {
            $this->auth->logout((int) $user['id'], $user['username'], $token, $request->getServerParams()['REMOTE_ADDR'] ?? null);
        }
        $response = $this->expireCookie($response);
        return $response->withHeader('Location', '/login')->withStatus(303);
    }

    public function resetForm(Request $request, Response $response): Response
    {
        return $this->renderGuest($response, 'auth/reset.php', ['error' => null, 'notice' => null, 'form' => []]);
    }

    public function resetRequest(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $identifier = trim((string) ($data['identifier'] ?? ''));
        if ($identifier === '') {
            return $this->renderGuest($response, 'auth/reset.php', ['error' => 'Identifier is required.', 'notice' => null, 'form' => $data]);
        }
        $result = $this->auth->requestPasswordReset($identifier, $request->getServerParams()['REMOTE_ADDR'] ?? null);
        return $this->renderGuest($response, 'auth/reset.php', ['error' => null, 'notice' => $result['message'], 'form' => $data]);
    }

    private function renderGuest(Response $response, string $template, array $data): Response
    {
        return $this->view->render($response, $template, $data);
    }

    private function withSessionCookie(Response $response, string $token): Response
    {
        $secure = isset($_SERVER['HTTPS']);
        return $response->withHeader('Set-Cookie', sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Lax%s%s',
            $this->sessions->cookieName(),
            $token,
            $secure ? '; Secure' : '',
            $this->config['session_lifetime'] > 0 ? '; Max-Age=' . $this->config['session_lifetime'] : ''
        ));
    }

    private function expireCookie(Response $response): Response
    {
        return $response->withHeader('Set-Cookie', sprintf(
            '%s=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0',
            $this->sessions->cookieName()
        ));
    }
}
