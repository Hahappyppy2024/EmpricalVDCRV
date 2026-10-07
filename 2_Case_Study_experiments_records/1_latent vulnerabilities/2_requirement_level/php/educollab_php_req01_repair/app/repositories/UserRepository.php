<?php

declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash, display_name, is_admin, created_at)
             VALUES (:username, :password_hash, :display_name, :is_admin, :created_at)'
        );
        $stmt->execute([
            ':username' => $data['username'],
            ':password_hash' => $data['password_hash'],
            ':display_name' => $data['display_name'],
            ':is_admin' => !empty($data['is_admin']) ? 1 : 0,
            ':created_at' => date('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT user_id, username, display_name, is_admin, created_at FROM users WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT user_id, username, display_name, created_at FROM users ORDER BY user_id ASC');
        return $stmt->fetchAll();
    }

    public function setAdminStatus(int $userId, bool $isAdmin): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_admin = :is_admin WHERE user_id = :user_id');
        return $stmt->execute([
            ':is_admin' => $isAdmin ? 1 : 0,
            ':user_id' => $userId,
        ]);
    }
}