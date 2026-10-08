<?php

declare(strict_types=1);

namespace Shop\Service;

use PDO;
use Shop\Auth;
use Shop\DomainException;
use Shop\MailService;
use Shop\Repository\UserRepository;
use Shop\Validation;
use Shop\ValidationException;

/**
 * SHOP-01 Accounts: registration, sign-in, profile management, account recovery.
 */
final class AccountService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_WINDOW = 900; // seconds

    public function __construct(
        private PDO $pdo,
        private Auth $auth,
        private UserRepository $users,
        private MailService $mail
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public function register(array $data): array
    {
        return $this->auth->register(
            (string) ($data['email'] ?? ''),
            (string) ($data['password'] ?? ''),
            (string) ($data['name'] ?? '')
        );
    }

    public function login(string $email, string $password, string $ip = ''): array
    {
        $user = $this->auth->login($email, $password);
        if ($user === null) {
            if ($this->recordLoginFailure($email, $ip)) {
                throw new DomainException('Too many failed login attempts. Please try again later.', 429);
            }
            throw new DomainException('Invalid email or password.', 401);
        }
        $this->clearLoginFailures($email, $ip);
        return $user;
    }

    /**
     * Record a failed login attempt and report whether the account is now
     * throttled (brute-force protection).
     */
    private function recordLoginFailure(string $email, string $ip): bool
    {
        $email = strtolower(trim($email));
        $stmt = $this->pdo->prepare(
            'SELECT attempts, last_attempt_at FROM login_attempts WHERE email = ? AND ip_address = ?'
        );
        $stmt->execute([$email, $ip]);
        $row = $stmt->fetch();
        $attempts = 1;
        if ($row !== false) {
            $attempts = (int) $row['attempts'] + 1;
            if (strtotime((string) $row['last_attempt_at']) < time() - self::LOGIN_LOCK_WINDOW) {
                $attempts = 1;
            }
        }
        $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, attempts, last_attempt_at) VALUES (?, ?, ?, ?)
             ON CONFLICT(email, ip_address) DO UPDATE SET attempts = excluded.attempts, last_attempt_at = excluded.last_attempt_at'
        )->execute([$email, $ip, $attempts, date('Y-m-d H:i:s')]);
        return $attempts >= self::MAX_LOGIN_ATTEMPTS;
    }

    private function clearLoginFailures(string $email, string $ip): void
    {
        $this->pdo->prepare('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?')
            ->execute([strtolower(trim($email)), $ip]);
    }

    public function requestReset(string $email): bool
    {
        $user = $this->users->byEmail(strtolower(trim($email)));
        if ($user === null) {
            // Deterministic outcome even when the account does not exist.
            return false;
        }
        $token = bin2hex(random_bytes(24));
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', time() + 3600)]);

        $resetUrl = (string) (getenv('APP_URL') ?: 'http://localhost:8080') . '/reset/' . $token;
        $this->mail->send(
            $user['email'],
            'Reset your P03 E-commerce password',
            "Hi {$user['name']},\n\nUse the following link to reset your password (valid for 1 hour):\n{$resetUrl}\n\nIf you did not request this, you can ignore this email."
        );
        return true;
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id FROM password_resets WHERE token = ? AND used = 0 AND expires_at > datetime(\'now\')'
        );
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        if ($reset === false) {
            throw new DomainException('This reset link is invalid or has expired.', 400);
        }
        if (strlen($newPassword) < 8) {
            throw new ValidationException(['password' => 'Password must be at least 8 characters.']);
        }
        $this->users->setPassword((int) $reset['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
        $this->pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$reset['id']]);
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     */
    public function updateProfile(array $user, array $data): array
    {
        $errors = Validation::required($data, 'name');
        Validation::throw($errors);
        if (!empty($data['password']) || !empty($data['current_password'])) {
            $current = (string) ($data['current_password'] ?? '');
            if ($current === '' || !password_verify($current, $user['password_hash'])) {
                throw new ValidationException(['current_password' => 'Current password is incorrect.']);
            }
            if (strlen((string) ($data['password'] ?? '')) < 8) {
                throw new ValidationException(['password' => 'New password must be at least 8 characters.']);
            }
            $this->users->setPassword((int) $user['id'], password_hash((string) $data['password'], PASSWORD_DEFAULT));
        }
        $this->users->updateProfile((int) $user['id'], $data);
        return $this->users->byId((int) $user['id']) ?? [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listUsers(): array
    {
        return $this->users->all();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateUser(array $admin, int $id, array $data): array
    {
        if ((int) $admin['id'] === $id) {
            throw new DomainException('You cannot change your own account here.', 422);
        }
        $user = $this->users->byId($id);
        if ($user === null) {
            throw new DomainException('User not found.', 404);
        }
        if (array_key_exists('active', $data)) {
            $this->users->setActive($id, $data['active'] ? 1 : 0);
        }
        if (array_key_exists('role', $data) && $data['role'] !== '') {
            $roleId = $this->users->roleId((string) $data['role']);
            if ($roleId === 0) {
                throw new ValidationException(['role' => 'Unknown role.']);
            }
            $this->users->setRole($id, $roleId);
        }
        return $this->users->byId($id) ?? [];
    }
}
