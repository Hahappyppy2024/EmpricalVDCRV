<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\SessionService;
use App\Database;
use App\Repositories\UserRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class AuthController
{
    public function __construct(
        private SessionService $sessions,
        private UserRepository $users,
    ) {}

    public function loginPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) \App\Middleware\CsrfMiddleware::ensureSession();
        $csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
        if ($this->sessions->current()) {
            // Still render the page with CSRF so callers can pick it up
            return View::render($response, 'auth/login', [
                'error' => $request->getQueryParams()['error'] ?? null,
                'flash' => Flash::pull(),
                'csrf'  => $csrf,
                'alreadyLoggedIn' => true,
            ]);
        }
        return View::render($response, 'auth/login', [
            'error' => $request->getQueryParams()['error'] ?? null,
            'flash' => Flash::pull(),
            'csrf'  => $csrf,
        ]);
    }

    public function registerPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) \App\Middleware\CsrfMiddleware::ensureSession();
        $csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
        if ($this->sessions->current()) {
            return View::redirect($response, '/dashboard');
        }
        return View::render($response, 'auth/register', [
            'error' => $request->getQueryParams()['error'] ?? null,
            'flash' => Flash::pull(),
            'csrf'  => $csrf,
            'old'   => [],
        ]);
    }

    public function loginPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $login = trim((string)($body['login'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if (!Validator::nonEmpty($login) || !Validator::nonEmpty($password)) {
            Flash::set('error', 'Login and password are required');
            return View::redirect($response, '/login?error=validation');
        }

        $user = $this->users->findByUsernameOrEmail($login);
        if (!$user || !password_verify($password, $user['password_hash']) || !$user['is_active']) {
            AuditLogger::log(null, 'guest', 'auth.login.failed', 'user', null, "login=$login", $this->clientIp($request));
            Flash::set('error', 'Invalid credentials');
            return View::redirect($response, '/login?error=invalid');
        }

        $this->sessions->login((int)$user['id'], $this->clientIp($request), $request->getHeaderLine('User-Agent'));
        AuditLogger::log((int)$user['id'], $user['role'], 'auth.login.success', 'user', (int)$user['id'], null, $this->clientIp($request));

        Flash::set('success', 'Welcome back, ' . ($user['full_name'] ?: $user['username']));
        return View::redirect($response, '/dashboard');
    }

    public function registerPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $username = trim((string)($body['username'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $confirm  = (string)($body['password_confirm'] ?? '');
        $fullName = trim((string)($body['full_name'] ?? ''));

        $old = compact('username', 'email', 'fullName');

        if (!Validator::username($username)) { Flash::set('error', 'Username must be 3-32 chars, alphanumeric/_-.'); return View::redirect($response, '/register?error=username'); }
        if (!Validator::email($email))       { Flash::set('error', 'Email is invalid');                  return View::redirect($response, '/register?error=email'); }
        if (strlen($password) < 8)            { Flash::set('error', 'Password must be at least 8 chars'); return View::redirect($response, '/register?error=password'); }
        if ($password !== $confirm)           { Flash::set('error', 'Passwords do not match');          return View::redirect($response, '/register?error=password'); }

        if ($this->users->findByUsernameOrEmail($username) || $this->users->findByUsernameOrEmail($email)) {
            Flash::set('error', 'Username or email already in use');
            return View::redirect($response, '/register?error=duplicate');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $uid = $this->users->create($username, $email, $hash, $fullName, 'customer', 1);
        AuditLogger::log($uid, 'customer', 'auth.register', 'user', $uid, $username, $this->clientIp($request));
        $this->sessions->login($uid, $this->clientIp($request), $request->getHeaderLine('User-Agent'));
        Flash::set('success', 'Account created');
        return View::redirect($response, '/dashboard');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user) AuditLogger::log((int)$user['id'], $user['role'], 'auth.logout', 'user', (int)$user['id'], null, $this->clientIp($request));
        $this->sessions->logout();
        return View::redirect($response, '/login');
    }

    public function changePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $current = (string)($body['current_password'] ?? '');
        $new = (string)($body['new_password'] ?? '');
        $confirm = (string)($body['new_password_confirm'] ?? '');

        $full = $this->users->findById((int)$user['id']);
        if (!$full || !password_verify($current, $full['password_hash'])) {
            Flash::set('error', 'Current password is incorrect');
            return View::redirect($response, '/account?error=current');
        }
        if (strlen($new) < 8 || $new !== $confirm) {
            Flash::set('error', 'New password invalid or does not match');
            return View::redirect($response, '/account?error=new');
        }
        $this->users->updatePassword((int)$user['id'], password_hash($new, PASSWORD_BCRYPT));
        AuditLogger::log((int)$user['id'], $user['role'], 'auth.password.change', 'user', (int)$user['id'], null, $this->clientIp($request));
        Flash::set('success', 'Password updated');
        return View::redirect($response, '/account');
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();
        return $params['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}