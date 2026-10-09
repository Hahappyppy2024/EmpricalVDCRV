<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['password_hash'],
            $data['role'],
            $data['status'] ?? 'active',
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        $allowed = ['name', 'email', 'role', 'status'];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = "$key = ?";
                $params[] = $fields[$key];
            }
        }
        if ($sets === []) {
            return;
        }
        $sets[] = "updated_at = datetime('now')";
        $params[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        Database::pdo()->prepare($sql)->execute($params);
    }

    public function updatePassword(int $id, string $hash): void
    {
        Database::pdo()->prepare('UPDATE users SET password_hash = ?, updated_at = datetime(\'now\') WHERE id = ?')
            ->execute([$hash, $id]);
    }

    public function listAll(): array
    {
        return Database::pdo()->query('SELECT * FROM users ORDER BY id')->fetchAll();
    }
}