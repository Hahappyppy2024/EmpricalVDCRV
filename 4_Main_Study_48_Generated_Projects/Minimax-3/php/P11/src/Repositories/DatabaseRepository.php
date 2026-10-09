<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class DatabaseRepository
{
    public function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, db_name, db_user, size_mb, created_at FROM databases WHERE user_id = ? ORDER BY id');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM databases WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function existsForUser(int $userId, string $dbName): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM databases WHERE user_id = ? AND db_name = ?');
        $stmt->execute([$userId, $dbName]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(int $userId, string $dbName, string $dbUser, string $passHash): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO databases (user_id, db_name, db_user, db_pass_hash, size_mb) VALUES (?, ?, ?, ?, 0)'
        );
        $stmt->execute([$userId, $dbName, $dbUser, $passHash]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function updatePassword(int $id, int $userId, string $hash): void
    {
        $stmt = Database::pdo()->prepare('UPDATE databases SET db_pass_hash = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$hash, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM databases WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}