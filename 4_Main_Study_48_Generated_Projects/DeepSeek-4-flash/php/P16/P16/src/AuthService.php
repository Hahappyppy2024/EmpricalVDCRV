<?php

declare(strict_types=1);

namespace App;

final class AuthService
{
    public const ROLES = ['admin', 'operator', 'viewer'];

    public function __construct(
        private readonly Database $db,
        private readonly SessionService $sessions
    ) {
    }

    public function register(array $data, string $ip): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $fullName = trim((string) ($data['full_name'] ?? $username));

        $validator = (new Validator())
            ->required($data, 'username', 'email', 'password', 'full_name')
            ->string(['username' => $username], 'username', 50, true)
            ->email(['email' => $email], 'email', true)
            ->string(['full_name' => $fullName], 'full_name', 120, true);
        if (mb_strlen($password) < 8) {
            $validator->addError('password', 'Password must be at least 8 characters.');
        }
        $validator->unique($this->db, 'users', 'username', $username)
            ->unique($this->db, 'users', 'email', $email);
        $validator->throwIfInvalid();

        $userId = $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'full_name' => $fullName,
            'role' => 'operator',
            'active' => 1,
            'created_at' => $this->db->now(),
        ]);
        $this->db->insert('account_access', [
            'user_id' => $userId,
            'username' => $username,
            'action' => 'register',
            'outcome' => 'success',
            'ip_address' => mb_substr($ip, 0, 64),
            'details' => 'New operator account created via registration.',
            'created_at' => $this->db->now(),
        ]);

        return $this->openUser($userId, $ip);
    }

    public function login(string $username, string $password, string $ip): array
    {
        $user = $this->db->fetchOne('SELECT * FROM users WHERE username = ?', [trim($username)]);
        $valid = $user !== null && (int) $user['active'] === 1 && password_verify($password, $user['password_hash']);
        $this->db->insert('account_access', [
            'user_id' => $valid ? (int) $user['id'] : null,
            'username' => trim($username),
            'action' => 'login',
            'outcome' => $valid ? 'success' : 'failure',
            'ip_address' => mb_substr($ip, 0, 64),
            'details' => $valid ? 'Sign-in succeeded.' : 'Invalid credentials or disabled account.',
            'created_at' => $this->db->now(),
        ]);

        if (!$valid) {
            throw new ValidationException('Invalid username or password.');
        }

        return $this->openUser((int) $user['id'], $ip);
    }

    /** @return array{user: array<string, mixed>, token: string} */
    private function openUser(int $userId, string $ip): array
    {
        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
        $token = $this->sessions->createSession($userId, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');

        return ['user' => $user, 'token' => $token];
    }

    public function logout(?string $token, array $user): void
    {
        $this->sessions->destroySession($token);
        $this->db->insert('account_access', [
            'user_id' => (int) ($user['id'] ?? null),
            'username' => (string) ($user['username'] ?? ''),
            'action' => 'logout',
            'outcome' => 'success',
            'ip_address' => mb_substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64),
            'details' => 'Sign-out completed; session destroyed.',
            'created_at' => $this->db->now(),
        ]);
    }
}
