<?php
declare(strict_types=1);

namespace LMS\Auth;

use LMS\Http\Session;
use PDO;

/**
 * Authentication service: register, sign-in, password recovery, sign-out.
 *
 * Implements LMS-01 "Account access" semantics:
 *   - Server-side sessions identified by an HTTP-only cookie
 *   - Persistent session records are stored in SQLite (`sessions` table)
 *   - Validation produces deterministic, non-leaky error messages
 */
final class AuthService
{
    public function __construct(
        private PDO $pdo,
        private Session $session,
        private array $config
    ) {}

    public function currentUser(): ?array
    {
        $userId = $this->session->get('user_id');
        if (!$userId) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, username, email, full_name, role, status FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active') {
            $this->signOut();
            return null;
        }
        return $user;
    }

    public function isAuthenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    public function requireRole(array $roles): array
    {
        $user = $this->currentUser();
        if (!$user) {
            throw new HttpException(401, 'Authentication required.');
        }
        if (!in_array($user['role'], $roles, true)) {
            throw new HttpException(403, 'Insufficient privileges.');
        }
        return $user;
    }

    /**
     * Register a new visitor.
     *
     * @return array{user: array, errors: array<string,string>}
     */
    public function register(array $input): array
    {
        $errors = $this->validateRegistration($input);
        if (!empty($errors)) {
            return ['user' => null, 'errors' => $errors];
        }
        $hash = password_hash($input['password'], PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, full_name, password_hash, role)
             VALUES (?, ?, ?, ?, ?)'
        );
        try {
            $stmt->execute([
                $input['username'],
                $input['email'],
                $input['full_name'],
                $hash,
                $input['role'] ?? 'student',
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return ['user' => null, 'errors' => ['form' => 'Username or email already exists.']];
            }
            throw $e;
        }
        $id = (int)$this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT id, username, email, full_name, role, status FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        $this->audit(null, 'auth.register', 'user', $id, ['username' => $user['username'], 'role' => $user['role']]);
        return ['user' => $user, 'errors' => []];
    }

    /**
     * @return array{user: array|null, error: ?string}
     */
    public function signIn(string $identity, string $password): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, email, full_name, password_hash, role, status
             FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $stmt->execute([$identity, $identity]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->logAccess(null, 'signin.unknown', $identity);
            return ['user' => null, 'error' => 'Invalid credentials.'];
        }
        if ($row['status'] !== 'active') {
            $this->logAccess((int)$row['id'], 'signin.suspended');
            return ['user' => null, 'error' => 'Account is not active.'];
        }
        if (!password_verify($password, $row['password_hash'])) {
            $this->logAccess((int)$row['id'], 'signin.bad_password');
            return ['user' => null, 'error' => 'Invalid credentials.'];
        }
        $this->session->regenerate();
        $this->session->set('user_id', (int)$row['id']);
        $this->recordSession((int)$row['id']);
        unset($row['password_hash']);
        $this->audit((int)$row['id'], 'auth.sign_in', 'user', (int)$row['id']);
        $this->logAccess((int)$row['id'], 'signin.ok');
        return ['user' => $row, 'error' => null];
    }

    public function signOut(): void
    {
        $userId = $this->session->get('user_id');
        if ($userId) {
            $sid = $this->session->id();
            $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
            $stmt->execute([$sid]);
            $this->audit((int)$userId, 'auth.sign_out', 'user', (int)$userId);
            $this->logAccess((int)$userId, 'signout');
        }
        $this->session->flush();
    }

    public function requestPasswordReset(string $identity): ?string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identity, $identity]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $ins = $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
        );
        $ins->execute([(int)$row['id'], $token, $expires]);
        $this->audit((int)$row['id'], 'auth.reset.request', 'user', (int)$row['id']);
        $this->logAccess((int)$row['id'], 'reset.request');
        return $token;
    }

    public function performPasswordReset(string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        $stmt = $this->pdo->prepare('SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['ok' => false, 'error' => 'Invalid reset token.'];
        }
        if ($row['used_at']) {
            return ['ok' => false, 'error' => 'Reset token already used.'];
        }
        if (strtotime($row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'Reset token expired.'];
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $upd = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$hash, $row['user_id']]);
        $mark = $this->pdo->prepare('UPDATE password_resets SET used_at = datetime(\'now\') WHERE id = ?');
        $mark->execute([$row['id']]);
        $this->audit((int)$row['user_id'], 'auth.reset.complete', 'user', (int)$row['user_id']);
        $this->logAccess((int)$row['user_id'], 'reset.complete');
        return ['ok' => true];
    }

    private function validateRegistration(array $input): array
    {
        $errors = [];
        if (empty($input['username']) || !preg_match('/^[a-zA-Z0-9_]{3,32}$/', $input['username'])) {
            $errors['username'] = 'Username must be 3-32 chars (letters, digits, underscore).';
        }
        if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required.';
        }
        if (empty($input['full_name']) || strlen($input['full_name']) < 2) {
            $errors['full_name'] = 'Full name is required.';
        }
        if (empty($input['password']) || strlen($input['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if (!in_array($input['role'] ?? 'student', ['student', 'instructor'], true)) {
            $errors['role'] = 'Role must be student or instructor.';
        }
        return $errors;
    }

    private function recordSession(int $userId): void
    {
        $sid = $this->session->id();
        if ($sid === '') {
            return;
        }
        $expires = date('Y-m-d H:i:s', time() + $this->config['session']['lifetime']);
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO sessions (id, user_id, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $sid,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $expires,
        ]);
    }

    private function audit(?int $actorId, string $action, string $targetType, ?int $targetId, array $payload = []): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_events (actor_id, action, target_type, target_id, payload_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$actorId, $action, $targetType, $targetId, json_encode($payload)]);
    }

    private function logAccess(?int $userId, string $event, ?string $detail = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO account_access_log (user_id, event, detail, ip) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $event, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
