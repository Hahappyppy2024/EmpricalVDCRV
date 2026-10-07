<?php

class UserRepo {
    public function __construct(private PDO $pdo) {}

    public function create(string $username, string $password, string $displayName): array {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users(username, password_hash, display_name, created_at, is_global_admin) VALUES (?,?,?,?,0)');
        $stmt->execute([$username, $hash, $displayName, now_iso()]);
        return $this->getById((int)$this->pdo->lastInsertId());
    }

    public function getById(int $userId): ?array {
        $stmt = $this->pdo->prepare('SELECT user_id, username, display_name, created_at, is_global_admin FROM users WHERE user_id=?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['is_global_admin'] = !empty($row['is_global_admin']);
        return $row;
    }

    public function getByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare('SELECT user_id, username, display_name, created_at, is_global_admin FROM users WHERE username=?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['is_global_admin'] = !empty($row['is_global_admin']);
        return $row;
    }

    public function getAuthByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username=?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['is_global_admin'] = !empty($row['is_global_admin']);
        return $row;
    }

    public function listAll(): array {
        $stmt = $this->pdo->query('SELECT user_id, username, display_name, created_at, is_global_admin FROM users ORDER BY user_id ASC');
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['is_global_admin'] = !empty($row['is_global_admin']);
        }
        return $rows;
    }
}