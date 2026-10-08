<?php

declare(strict_types=1);

namespace CloudFS\Services;

use CloudFS\Database\Database;

final class AuthService
{
    public function __construct(
        private Database $db,
        private SessionService $sessions,
        private ValidationService $validation
    ) {
    }

    public function register(string $username, string $email, string $password, string $fullName, ?string $ip): array
    {
        $errors = $this->validation->required(
            ['username' => $username, 'email' => $email, 'password' => $password, 'full_name' => $fullName],
            ['username', 'email', 'password', 'full_name']
        );
        if (!$errors && !$this->validation->username($username)) {
            $errors[] = 'Username must be 3-32 characters using letters, digits, dot, dash or underscore.';
        }
        if (!$errors && !$this->validation->email($email)) {
            $errors[] = 'Email address is not valid.';
        }
        if (!$errors && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!$errors) {
            if ($this->db->one('SELECT id FROM users WHERE username = ?', [$username])) {
                $errors[] = 'That username is already taken.';
            } elseif ($this->db->one('SELECT id FROM users WHERE email = ?', [$email])) {
                $errors[] = 'That email address is already registered.';
            }
        }
        if ($errors) {
            $this->logAccess(null, $username, 'register', 'failure', $ip);
            return ['ok' => false, 'errors' => $errors];
        }

        $quota = (int) $this->db->value("SELECT value FROM settings WHERE key = 'default_quota_bytes'") ?: 104857600;
        $id = $this->db->insert(
            'INSERT INTO users (username, email, password_hash, full_name, role, quota_bytes, created_at, updated_at) VALUES (?, ?, ?, ?, \'user\', ?, datetime(\'now\'), datetime(\'now\'))',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $quota]
        );
        $this->db->insert('INSERT INTO storage_quota (user_id, used_bytes, soft_limit, hard_limit) VALUES (?, 0, ?, ?)', [$id, $quota, $quota]);
        $this->logAccess((int) $id, $username, 'register', 'success', $ip);
        $this->logAudit($id, $username, 'account.registered', 'user', (string) $id, []);
        $session = $this->sessions->create($id, $ip, $this->userAgent());
        return ['ok' => true, 'user_id' => $id, 'session_token' => $session['token']];
    }

    public function login(string $identifier, string $password, ?string $ip): array
    {
        $errors = $this->validation->required(['identifier' => $identifier, 'password' => $password], ['identifier', 'password']);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }
        $user = $this->db->one('SELECT * FROM users WHERE username = ? OR email = ?', [$identifier, $identifier]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logAccess(null, $identifier, 'login', 'failure', $ip);
            $this->logAudit(null, $identifier, 'login.failed', 'session', null, ['identifier' => $identifier]);
            return ['ok' => false, 'errors' => ['Invalid username or password.']];
        }
        $this->logAccess((int) $user['id'], $user['username'], 'login', 'success', $ip);
        $this->logAudit((int) $user['id'], $user['username'], 'login', 'session', null, []);
        $session = $this->sessions->create((int) $user['id'], $ip, $this->userAgent());
        return ['ok' => true, 'user_id' => (int) $user['id'], 'session_token' => $session['token']];
    }

    public function logout(int $userId, string $username, string $token, ?string $ip): void
    {
        $this->logAccess($userId, $username, 'logout', 'success', $ip);
        $this->logAudit($userId, $username, 'logout', 'session', null, []);
        $this->sessions->destroy($token);
    }

    public function requestPasswordReset(string $identifier, ?string $ip): array
    {
        $user = $this->db->one('SELECT * FROM users WHERE username = ? OR email = ?', [$identifier, $identifier]);
        if (!$user) {
            $this->logAccess(null, $identifier, 'reset_request', 'failure', $ip);
            return ['ok' => true, 'message' => 'If that account exists, a reset instruction has been recorded.'];
        }
        $this->logAccess((int) $user['id'], $user['username'], 'reset_request', 'success', $ip);
        $this->logAudit((int) $user['id'], $user['username'], 'account.reset_requested', 'user', (string) $user['id'], []);
        return ['ok' => true, 'message' => 'If that account exists, a reset instruction has been recorded.'];
    }

    public function resetPassword(int $userId, string $username, string $newPassword, ?string $ip): array
    {
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'errors' => ['Password must be at least 8 characters.']];
        }
        $this->db->run('UPDATE users SET password_hash = ?, updated_at = datetime(\'now\') WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        $this->logAccess($userId, $username, 'reset', 'success', $ip);
        $this->logAudit($userId, $username, 'account.reset', 'user', (string) $userId, []);
        return ['ok' => true];
    }

    private function logAccess(?int $userId, string $username, string $action, string $status, ?string $ip): void
    {
        $this->db->insert(
            'INSERT INTO account_access (user_id, username, action, status, ip_address, created_at) VALUES (?, ?, ?, ?, ?, datetime(\'now\'))',
            [$userId, $username, $action, $status, $ip]
        );
    }

    private function logAudit(?int $userId, string $actor, string $action, string $targetType, ?string $targetId, array $metadata): void
    {
        $this->db->insert(
            'INSERT INTO audit_events (user_id, actor_name, action, target_type, target_id, ip_address, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$userId, $actor, $action, $targetType, $targetId, $this->ip(), json_encode($metadata, JSON_UNESCAPED_UNICODE)]
        );
    }

    private function ip(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function userAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}
