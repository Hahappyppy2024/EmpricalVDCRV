<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AccountAccessController
{
    public function showPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        $user = SessionService::user();
        $body = View::render('account_access', ['login_error' => null, 'register_error' => null, 'register_success' => null, 'reset_error' => null, 'reset_success' => null], $config);
        return View::html(View::layout('Account Access & Recovery', $body, $config, $user));
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        $body = $request->getParsedBody();
        $username = trim($body['username'] ?? '');
        $password = (string)($body['password'] ?? '');

        if ($username === '' || $password === '') {
            $html = View::render('account_access', ['login_error' => 'Username and password are required.', 'register_error' => null, 'register_success' => null, 'reset_error' => null, 'reset_success' => null], $config);
            return View::html(View::layout('Sign In', $html, $config, null));
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $username]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($password, $u['password_hash'])) {
            AuditService::log(null, 'auth.login.failed', 'user', $username, 'Invalid credentials');
            $html = View::render('account_access', ['login_error' => 'Invalid credentials.', 'register_error' => null, 'register_success' => null, 'reset_error' => null, 'reset_success' => null], $config);
            return View::html(View::layout('Sign In', $html, $config, null));
        }

        SessionService::login((int)$u['id']);
        AuditService::log((int)$u['id'], 'auth.login.success', 'user', (string)$u['id'], 'User signed in');
        View::flash('success', 'Welcome back, ' . $u['display_name']);
        return View::redirect('/dashboard');
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        $body = $request->getParsedBody();
        $username = trim($body['username'] ?? '');
        $email = trim($body['email'] ?? '');
        $displayName = trim($body['display_name'] ?? '');
        $password = (string)($body['password'] ?? '');
        $role = trim($body['role'] ?? 'author');

        if ($username === '' || $email === '' || $displayName === '' || strlen($password) < 6) {
            $html = View::render('account_access', ['login_error' => null, 'register_error' => 'All fields required. Password min 6 chars.', 'register_success' => null, 'reset_error' => null, 'reset_success' => null], $config);
            return View::html(View::layout('Account', $html, $config, null));
        }

        if (!in_array($role, ['author', 'reviewer'], true)) {
            $role = 'author';
        }

        $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $html = View::render('account_access', ['login_error' => null, 'register_error' => 'Username or email already taken.', 'register_success' => null, 'reset_error' => null, 'reset_success' => null], $config);
            return View::html(View::layout('Account', $html, $config, null));
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $i = Database::pdo()->prepare('INSERT INTO users (username, email, password_hash, display_name, roles) VALUES (?, ?, ?, ?, ?)');
        $i->execute([$username, $email, $hash, $displayName, $role]);
        $uid = (int)Database::pdo()->lastInsertId();
        SessionService::login($uid);
        AuditService::log($uid, 'auth.register', 'user', (string)$uid, 'New registration');
        View::flash('success', 'Account created. Welcome!');
        return View::redirect('/dashboard');
    }

    public function requestReset(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        $body = $request->getParsedBody();
        $identifier = trim($body['identifier'] ?? '');

        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        $success = 'If an account matches, a reset token has been generated.';
        $resetSuccess = $success;
        if ($user) {
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $ins = Database::pdo()->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
            $ins->execute([$user['id'], $token, $expires]);
            $resetSuccess .= ' Token (demo): ' . $token;
        }

        $html = View::render('account_access', ['login_error' => null, 'register_error' => null, 'register_success' => null, 'reset_error' => null, 'reset_success' => $resetSuccess], $config);
        return View::html(View::layout('Account Recovery', $html, $config, null));
    }

    public function performReset(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        $body = $request->getParsedBody();
        $token = trim($body['token'] ?? '');
        $newPassword = (string)($body['new_password'] ?? '');

        if ($token === '' || strlen($newPassword) < 6) {
            $html = View::render('account_access', ['login_error' => null, 'register_error' => null, 'register_success' => null, 'reset_error' => 'Token and password (min 6) required.', 'reset_success' => null], $config);
            return View::html(View::layout('Account Recovery', $html, $config, null));
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM password_reset_tokens WHERE token = ? AND used = 0');
        $stmt->execute([$token]);
        $tok = $stmt->fetch();

        if (!$tok || strtotime($tok['expires_at']) < time()) {
            $html = View::render('account_access', ['login_error' => null, 'register_error' => null, 'register_success' => null, 'reset_error' => 'Invalid or expired token.', 'reset_success' => null], $config);
            return View::html(View::layout('Account Recovery', $html, $config, null));
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $u = Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $u->execute([$hash, $tok['user_id']]);
        $m = Database::pdo()->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id = ?');
        $m->execute([$tok['id']]);

        $html = View::render('account_access', ['login_error' => null, 'register_error' => null, 'register_success' => 'Password reset successful. You may sign in.', 'reset_error' => null, 'reset_success' => null], $config);
        return View::html(View::layout('Account Recovery', $html, $config, null));
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        SessionService::logout();
        return View::redirect('/login');
    }
}