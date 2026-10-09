<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class UserRepository
{
    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUsernameOrEmail(string $login): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $username, string $email, string $passwordHash, string $fullName, string $role, ?int $planId): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (username, email, password_hash, full_name, role, plan_id, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$username, $email, $passwordHash, $fullName, $role, $planId]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function setRole(int $id, string $role): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $id]);
    }

    public function setPlan(int $id, ?int $planId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET plan_id = ? WHERE id = ?');
        $stmt->execute([$planId, $id]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
    }

    public function all(): array
    {
        return Database::pdo()->query('SELECT u.*, p.name AS plan_name FROM users u LEFT JOIN plans p ON p.id = u.plan_id ORDER BY u.id')->fetchAll();
    }
}