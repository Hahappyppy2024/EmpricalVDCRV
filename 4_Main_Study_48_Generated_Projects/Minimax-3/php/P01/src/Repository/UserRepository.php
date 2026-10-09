<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email, full_name, role, status, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function byUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email, full_name, role, status FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function byEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email, full_name, role, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listByRole(string $role): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, email, full_name, role, status, created_at FROM users
             WHERE role = ? ORDER BY full_name'
        );
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        return $this->pdo
            ->query('SELECT id, username, email, full_name, role, status, created_at FROM users ORDER BY id')
            ->fetchAll();
    }

    public function updateRole(int $id, string $role): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        return $stmt->execute([$role, $id]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status = \'deleted\' WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
