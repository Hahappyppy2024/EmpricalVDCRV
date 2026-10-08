<?php

declare(strict_types=1);

namespace P13;

/**
 * Authentication service: registration, sign-in, password change and the
 * persistent access records (AccountAccess) used by MAIL-01.
 */
final class Auth
{
    public function __construct(
        private Database $db,
        private Session $session,
        private Config $config
    ) {
    }

    /**
     * Register a new account. Deterministic errors, no duplicate usernames/emails.
     *
     * @return array{ok: bool, user?: array<string, mixed>, errors?: array<string, string>, message?: string}
     */
    public function register(
        string $username,
        string $email,
        string $password,
        string $displayName,
        string $role = 'mail_user',
        ?int $domainId = null,
        ?string $ip = null,
        ?string $ua = null
    ): array {
        $username = trim($username);
        $email = trim($email);
        $displayName = trim($displayName === '' ? $username : $displayName);

        $errors = [];
        if ($username === '' || mb_strlen($username) > 64) {
            $errors['username'] = 'Username is required (max 64 chars).';
        }
        if (!Validation::email($email) || mb_strlen($email) > 255) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if (!Validation::role($role)) {
            $errors['role'] = 'Invalid role.';
        }
        $exists = $this->db->row(
            'SELECT id FROM users WHERE username = ? OR email = ?',
            [$username, $email]
        );
        if ($exists !== null) {
            $errors['account'] = 'An account with this username or email already exists.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->db->execute(
            'INSERT INTO users (username, email, password_hash, display_name, role, domain_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $displayName,
                $role,
                $domainId,
                'active',
            ]
        );
        $userId = $this->db->lastInsertId();

        $this->db->execute(
            'INSERT INTO account_access (user_id, type, status, message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$userId, 'register', 'success', 'Account registered', $ip ?? '127.0.0.1', $ua ?? 'unknown']
        );
        $this->audit($userId, $role, 'user.register', 'User', (string) $userId, ['email' => $email], $ip);

        $user = $this->db->row('SELECT * FROM users WHERE id = ?', [$userId]);
        return ['ok' => true, 'user' => $user, 'message' => 'Account created.'];
    }

    /**
     * Attempt a sign-in. On success a persistent session is created.
     *
     * @return array{ok: bool, user?: array<string, mixed>, errors?: array<string, string>}
     */
    public function attempt(string $login, string $password, string $ip, string $ua): array
    {
        $user = $this->db->row(
            'SELECT * FROM users WHERE (username = ? OR email = ?) AND status = \'active\'',
            [$login, $login]
        );
        $failed = function () use ($login, $ip, $ua) {
            $this->db->execute(
                'INSERT INTO account_access (user_id, type, status, message, ip_address, user_agent, created_at)
                 VALUES (NULL, ?, ?, ?, ?, ?, datetime(\'now\'))',
                ['login', 'failure', 'Invalid credentials', $ip, $ua]
            );
            $this->audit(null, null, 'auth.login_failed', 'User', (string) $login, [], $ip);
        };

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $failed();
            return ['ok' => false, 'errors' => ['credentials' => 'Invalid credentials.']];
        }

        $token = $this->session->create((int) $user['id'], $ip, $ua);
        $this->db->execute(
            'INSERT INTO account_access (user_id, type, status, message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [(int) $user['id'], 'login', 'success', 'Signed in', $ip, $ua]
        );
        $this->audit((int) $user['id'], (string) $user['role'], 'auth.login', 'User', (string) $user['id'], [], $ip);

        $user['session_token'] = $token;
        return ['ok' => true, 'user' => $user];
    }

    /**
     * Sign out the current session and record the event.
     */
    public function signOut(int $userId, string $role, string $ip, string $ua): void
    {
        $this->db->execute(
            'INSERT INTO account_access (user_id, type, status, message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$userId, 'logout', 'success', 'Signed out', $ip, $ua]
        );
        $this->audit($userId, $role, 'auth.logout', 'User', (string) $userId, [], $ip);
        $this->session->destroy();
    }

    /**
     * Change the current user's password.
     *
     * @return array{ok: bool, message?: string, errors?: array<string, string>}
     */
    public function changePassword(int $userId, string $current, string $new): array
    {
        $user = $this->db->row('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($user === null) {
            return ['ok' => false, 'errors' => ['account' => 'Unknown account.']];
        }
        if (!password_verify($current, (string) $user['password_hash'])) {
            return ['ok' => false, 'errors' => ['current' => 'Current password is incorrect.']];
        }
        if (strlen($new) < 8) {
            return ['ok' => false, 'errors' => ['new' => 'New password must be at least 8 characters.']];
        }
        $this->db->execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($new, PASSWORD_DEFAULT), $userId]
        );
        $this->db->execute(
            'INSERT INTO account_access (user_id, type, status, message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$userId, 'password_change', 'success', 'Password changed', '127.0.0.1', 'console']
        );
        $this->audit($userId, (string) $user['role'], 'auth.password_change', 'User', (string) $userId, [], null);
        return ['ok' => true, 'message' => 'Password updated.'];
    }

    /**
     * List AccountAccess records visible to the current user.
     */
    public function accessHistory(int $userId, int $limit = 50): array
    {
        return $this->db->select(
            'SELECT id, user_id, type, status, message, ip_address, created_at
               FROM account_access
              WHERE user_id = ?
              ORDER BY id DESC
              LIMIT ?',
            [$userId, $limit]
        );
    }

    private function audit(?int $userId, ?string $role, string $action, string $entityType, ?string $entityId, array $details, ?string $ip): void
    {
        $this->db->execute(
            'INSERT INTO audit_events (user_id, actor_username, actor_role, action, entity_type, entity_id, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [
                $userId,
                $this->usernameFor($userId),
                $role ?? 'guest',
                $action,
                $entityType,
                $entityId,
                json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $ip ?? '127.0.0.1',
            ]
        );
    }

    private function usernameFor(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }
        $row = $this->db->row('SELECT username FROM users WHERE id = ?', [$userId]);
        return $row['username'] ?? null;
    }
}
