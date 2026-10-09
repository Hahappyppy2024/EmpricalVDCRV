<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AccountAccessController
{
    public function __construct(
        private View $view,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->auth->isAuthenticated()) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        $data = ['csrf' => $this->csrf->token(), 'error' => null, 'identity' => ''];
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $data['identity'] = trim((string)($body['identity'] ?? ''));
            $result = $this->auth->signIn($data['identity'], (string)($body['password'] ?? ''));
            if ($result['user']) {
                $this->session->setFlash('notice', 'Signed in as ' . $result['user']['full_name']);
                $next = (string)($body['next'] ?? '/dashboard');
                return $response->withHeader('Location', $next)->withStatus(302);
            }
            $data['error'] = $result['error'];
        }
        return $this->view->render($response, 'login.php', $data);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->auth->isAuthenticated()) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        $data = [
            'csrf' => $this->csrf->token(),
            'errors' => [],
            'input' => ['username' => '', 'email' => '', 'full_name' => '', 'role' => 'student'],
        ];
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $input = [
                'username' => trim((string)($body['username'] ?? '')),
                'email' => trim((string)($body['email'] ?? '')),
                'full_name' => trim((string)($body['full_name'] ?? '')),
                'role' => (string)($body['role'] ?? 'student'),
                'password' => (string)($body['password'] ?? ''),
            ];
            $data['input'] = $input;
            $result = $this->auth->register($input);
            if ($result['user']) {
                $this->auth->signIn($result['user']['username'], $input['password']);
                $this->session->setFlash('notice', 'Account created.');
                return $response->withHeader('Location', '/dashboard')->withStatus(302);
            }
            $data['errors'] = $result['errors'];
        }
        return $this->view->render($response, 'register.php', $data);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->signOut();
        $this->session->setFlash('notice', 'Signed out.');
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function forgot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = ['csrf' => $this->csrf->token(), 'token' => null, 'identity' => ''];
        if (strtoupper($request->getMethod()) === 'POST') {
            $identity = trim((string)($request->getParsedBody()['identity'] ?? ''));
            $data['identity'] = $identity;
            $token = $this->auth->requestPasswordReset($identity);
            $data['token'] = $token; // returned for offline determinism
        }
        return $this->view->render($response, 'forgot.php', $data);
    }

    public function reset(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = ['csrf' => $this->csrf->token(), 'token' => $args['token'], 'ok' => false, 'error' => null];
        if (strtoupper($request->getMethod()) === 'POST') {
            $newPassword = (string)($request->getParsedBody()['password'] ?? '');
            $result = $this->auth->performPasswordReset((string)$args['token'], $newPassword);
            $data['ok'] = $result['ok'];
            $data['error'] = $result['error'] ?? null;
        }
        return $this->view->render($response, 'reset.php', $data);
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $template = match ($user['role']) {
            'admin' => 'dashboard_admin.php',
            'instructor' => 'dashboard_instructor.php',
            default => 'dashboard_student.php',
        };
        return $this->view->render($response, $template, [
            'user' => $user,
            'flash' => ['notice' => $this->session->takeFlash('notice')],
        ]);
    }
}
