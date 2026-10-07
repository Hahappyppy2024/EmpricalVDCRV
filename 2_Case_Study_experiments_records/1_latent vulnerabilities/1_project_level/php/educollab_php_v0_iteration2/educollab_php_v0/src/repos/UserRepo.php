<?php

class UserRepo {
    public function __construct(private PDO $pdo) {}

    public function create(string $username, string $password, string $displayName): array {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users(username, password_hash, display_name, created_at, is_global_admin) VALUES (?,?,?,?,0)'
        );
        $stmt->execute([$username, $hash, $displayName, now_iso()]);
        return $this->getById((int)$this->pdo->lastInsertId());
    }

    public function getById(int $userId): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, username, display_name, created_at, is_global_admin FROM users WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getAuthByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, username, password_hash, display_name, created_at, is_global_admin FROM users WHERE username = ?'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listAll(): array {
        return $this->pdo->query(
            'SELECT user_id, username, display_name, created_at, is_global_admin FROM users ORDER BY user_id DESC'
        )->fetchAll();
    }
}