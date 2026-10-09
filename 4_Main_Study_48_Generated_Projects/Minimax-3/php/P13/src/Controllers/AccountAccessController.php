<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Slim\Views\PhpRenderer;

class AccountAccessController
{
    public function __construct(
        private SessionService $session,
        private UserRepository $users,
        private PhpRenderer $view
    ) {
    }

    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->session->start();
        if ($this->session->currentUserId()) {
            return ResponseHelper::redirect($response, '/dashboard');
        }
        return $this->view->render($response, 'login.php', ['error' => null, 'old' => []]);
    }

    public function showRegister(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->session->start();
        if ($this->session->currentUserId()) {
            return ResponseHelper::redirect($response, '/dashboard');
        }
        return $this->view->render($response, 'register.php', ['error' => null, 'old' => []]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->session->start();
        $data = (array) $request->getParsedBody();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $request->getHeaderLine('User-Agent') ?: 'unknown';

        if ($username === '' || $password === '') {
            return $this->view->render($response->withStatus(422), 'login.php', [
                'error' => 'Username and password are required.',
                'old' => ['username' => $username],
            ]);
        }

        $user = $this->users->findByUsername($username);
        if (!$user) {
            $user = $this->users->findByEmail($username);
        }
        if (!$user || !password_verify($password, $user['password_hash'])) {
            if ($user) {
                $this->session->recordAccess((int) $user['id'], 'login', $ip, $ua, false, 'invalid password');
            }
            return $this->view->render($response->withStatus(401), 'login.php', [
                'error' => 'Invalid credentials.',
                'old' => ['username' => $username],
            ]);
        }

        if ($user['status'] !== 'active') {
            $this->session->recordAccess((int) $user['id'], 'login', $ip, $ua, false, 'account inactive');
            return $this->view->render($response->withStatus(403), 'login.php', [
                'error' => 'Account is not active.',
                'old' => ['username' => $username],
            ]);
        }

        $this->session->login((string) $user['id'], $ip, $ua);
        $this->session->recordAccess((int) $user['id'], 'login', $ip, $ua, true, 'successful login');
        $this->session->recordAudit((int) $user['id'], $user['role'], 'user.login', 'user', (string) $user['id'], 'login ok', $ip);

        return ResponseHelper::redirect($response, '/dashboard');
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->session->start();
        $data = (array) $request->getParsedBody();
        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $fullName = trim((string) ($data['full_name'] ?? ''));

        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
            $errors[] = 'Username must be 3-32 alphanumeric or underscore.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            return $this->view->render($response->withStatus(422), 'register.php', [
                'error' => implode(' ', $errors),
                'old' => compact('username', 'email', 'fullName'),
            ]);
        }

        if ($this->users->findByUsername($username)) {
            return $this->view->render($response->withStatus(409), 'register.php', [
                'error' => 'Username already taken.',
                'old' => compact('username', 'email', 'fullName'),
            ]);
        }
        if ($this->users->findByEmail($email)) {
            return $this->view->render($response->withStatus(409), 'register.php', [
                'error' => 'Email already registered.',
                'old' => compact('username', 'email', 'fullName'),
            ]);
        }

        $id = $this->users->create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $fullName ?: $username,
            'role' => 'mail_user',
            'mailbox_quota_mb' => 1024,
        ]);

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $request->getHeaderLine('User-Agent') ?: 'unknown';
        $this->session->recordAccess($id, 'register', $ip, $ua, true, 'registration ok');
        $this->session->recordAudit($id, 'mail_user', 'user.register', 'user', (string) $id, 'new account', $ip);

        return ResponseHelper::redirect($response, '/login?registered=1');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->session->start();
        $userId = $this->session->currentUserId();
        if ($userId) {
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
            $this->session->recordAudit($userId, 'self', 'user.logout', 'user', (string) $userId, 'logout ok', $ip);
        }
        $this->session->logout();
        return ResponseHelper::redirect($response, '/login');
    }

    public function showDashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        return $this->view->render($response, 'dashboard.php', ['user' => $user]);
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $pdo = \MailServer\Database\Database::connection();
        $stmt = $pdo->prepare('SELECT id, user_id, access_type, remote_ip, success, detail, created_at FROM account_access WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$user['id']]);
        $access = $stmt->fetchAll();
        return ResponseHelper::json($response, [
            'user' => ['id' => (int) $user['id'], 'username' => $user['username'], 'role' => $user['role']],
            'access_log' => $access,
        ]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $request->getHeaderLine('User-Agent') ?: 'unknown';
        if ($username === '' || $password === '') {
            return ResponseHelper::validationError($response, ['username' => 'required', 'password' => 'required']);
        }
        $this->session->start();
        $user = $this->users->findByUsername($username) ?? $this->users->findByEmail($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            if ($user) {
                $this->session->recordAccess((int) $user['id'], 'login.api', $ip, $ua, false, 'invalid password');
            }
            return ResponseHelper::stableError($response, 'invalid_credentials', 401);
        }
        $sid = $this->session->login((string) $user['id'], $ip, $ua);
        $this->session->recordAccess((int) $user['id'], 'login.api', $ip, $ua, true);
        $this->session->recordAudit((int) $user['id'], $user['role'], 'user.login.api', 'user', (string) $user['id'], 'api login ok', $ip);
        return ResponseHelper::json($response, ['ok' => true, 'sid' => $sid, 'user_id' => (int) $user['id'], 'role' => $user['role']]);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ((int) $args['id'] !== (int) $user['id']) {
            return ResponseHelper::stableError($response, 'forbidden_cross_user', 403);
        }
        $data = (array) $request->getParsedBody();
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $newPassword = (string) ($data['password'] ?? '');
        $pdo = \MailServer\Database\Database::connection();
        if ($fullName !== '') {
            $pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$fullName, $user['id']]);
        }
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                return ResponseHelper::validationError($response, ['password' => 'too_short']);
            }
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_BCRYPT), $user['id']]);
        }
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'user.update', 'user', (string) $user['id'], 'profile updated', $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}