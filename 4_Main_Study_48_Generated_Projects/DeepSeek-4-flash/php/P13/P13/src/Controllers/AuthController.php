<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Auth;
use P13\Config;
use P13\Database;
use P13\Session;
use P13\Validation;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Authentication / account access controller (MAIL-01).
 * Handles browser forms and the JSON auth API.
 */
final class AuthController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private Auth $auth,
        private \P13\View $view,
        private Config $config
    ) {
        parent::__construct($db, $session);
    }

    public function me(Request $request, Response $response): Response
    {
        if ($this->session->isGuest()) {
            return $this->error($response, 'Unauthenticated.', 401);
        }
        $user = $this->user();
        return $this->ok($response, [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'display_name' => $user['display_name'],
                'role' => $user['role'],
                'domain_id' => $user['domain_id'],
                'status' => $user['status'],
            ],
        ]);
    }

    public function apiLogin(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $login = trim((string) ($data['login'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($login === '' || $password === '') {
            return $this->error($response, 'Login and password are required.', 422);
        }
        $result = $this->auth->attempt($login, $password, client_ip($request), client_ua($request));
        if (!$result['ok']) {
            return $this->error($response, 'Invalid credentials.', 401);
        }
        $token = $result['user']['session_token'];
        $response = $this->ok($response, [
            'user' => [
                'id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'email' => $result['user']['email'],
                'display_name' => $result['user']['display_name'],
                'role' => $result['user']['role'],
            ],
        ]);
        return $this->attachSessionCookie($response, $token);
    }

    public function apiRegister(Request $request, Response $response): Response
    {
        $data = $this->body($request);
        $result = $this->auth->register(
            (string) ($data['username'] ?? ''),
            (string) ($data['email'] ?? ''),
            (string) ($data['password'] ?? ''),
            (string) ($data['display_name'] ?? ''),
            'mail_user',
            null,
            client_ip($request),
            client_ua($request)
        );
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['user' => $result['user']], 201);
    }

    public function apiLogout(Request $request, Response $response): Response
    {
        $user = $this->user();
        if ($user !== []) {
            $this->auth->signOut((int) $user['id'], (string) $user['role'], client_ip($request), client_ua($request));
        }
        $response = $this->ok($response, ['message' => 'Signed out.']);
        return $response->withAddedHeader('Set-Cookie', $this->session->cookieName() . '=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0');
    }

    public function showLogin(Request $request, Response $response): Response
    {
        if ($this->session->authenticated()) {
            return redirect_response($response, '/dashboard');
        }
        $csrf = $this->session->csrfToken();
        $html = $this->view->render('auth/login', [
            'title' => 'Sign in',
            'user' => null,
            'active' => 'login',
            'csrf' => $csrf,
        ]);
        $response->getBody()->write($html);
        return $this->withGuestCsrf($response, $csrf)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function postLogin(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $login = trim((string) ($data['login'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($login === '' || $password === '') {
            return $this->renderLoginError($response, 'Login and password are required.');
        }
        $result = $this->auth->attempt($login, $password, client_ip($request), client_ua($request));
        if (!$result['ok']) {
            return $this->renderLoginError($response, 'Invalid credentials.');
        }
        $response = redirect_response($response, '/dashboard');
        return $this->attachSessionCookie($response, (string) $result['user']['session_token']);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        if ($this->session->authenticated()) {
            return redirect_response($response, '/dashboard');
        }
        $csrf = $this->session->csrfToken();
        $html = $this->view->render('auth/register', [
            'title' => 'Create account',
            'user' => null,
            'active' => 'register',
            'csrf' => $csrf,
        ]);
        $response->getBody()->write($html);
        return $this->withGuestCsrf($response, $csrf)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function postRegister(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $result = $this->auth->register(
            (string) ($data['username'] ?? ''),
            (string) ($data['email'] ?? ''),
            (string) ($data['password'] ?? ''),
            (string) ($data['display_name'] ?? ''),
            'mail_user',
            null,
            client_ip($request),
            client_ua($request)
        );
        if (!$result['ok']) {
            return $this->renderRegisterError($response, $result['errors'] ?? []);
        }
        $html = $this->view->render('auth/register_done', [
            'title' => 'Account created',
            'user' => null,
            'active' => 'register',
            'email' => $result['user']['email'],
        ]);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function postLogout(Request $request, Response $response): Response
    {
        $user = $this->user();
        if ($user !== []) {
            $this->auth->signOut((int) $user['id'], (string) $user['role'], client_ip($request), client_ua($request));
        }
        $response = redirect_response($response, '/login');
        return $response->withAddedHeader('Set-Cookie', $this->session->cookieName() . '=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0');
    }

    private function renderLoginError(Response $response, string $message): Response
    {
        $csrf = $this->session->csrfToken();
        $html = $this->view->render('auth/login', [
            'title' => 'Sign in',
            'user' => null,
            'active' => 'login',
            'csrf' => $csrf,
            'error' => $message,
        ]);
        $response->getBody()->write($html);
        return $this->withGuestCsrf($response, $csrf)->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(401);
    }

    private function renderRegisterError(Response $response, array $errors): Response
    {
        $csrf = $this->session->csrfToken();
        $html = $this->view->render('auth/register', [
            'title' => 'Create account',
            'user' => null,
            'active' => 'register',
            'csrf' => $csrf,
            'errors' => $errors,
        ]);
        $response->getBody()->write($html);
        return $this->withGuestCsrf($response, $csrf)->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(422);
    }

    private function withGuestCsrf(Response $response, string $token): Response
    {
        return $response->withAddedHeader(
            'Set-Cookie',
            $this->session->guestCsrfCookieName() . '=' . $token . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=86400'
        );
    }

    private function attachSessionCookie(Response $response, string $token): Response
    {
        $ttl = (int) $this->sessionCookieTtl();
        return $response->withAddedHeader(
            'Set-Cookie',
            $this->session->cookieName() . '=' . $token . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=' . $ttl
        );
    }

    private function sessionCookieTtl(): int
    {
        return (int) $this->config->get('session.ttl_seconds', 86400);
    }
}
