<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class BackupRepository
{
    public function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM backups WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        return Database::pdo()->query('SELECT b.*, u.username FROM backups b JOIN users u ON u.id = b.user_id ORDER BY b.id DESC')->fetchAll();
    }

    public function findOwned(int $id, int $userId, string $role): ?array
    {
        $sql = 'SELECT * FROM backups WHERE id = ?';
        $params = [$id];
        if ($role !== 'admin') {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function existsForUser(int $userId, string $filename): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM backups WHERE user_id = ? AND filename = ?');
        $stmt->execute([$userId, $filename]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(int $userId, string $scope, ?int $targetId, string $filename, int $size, ?string $note): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO backups (user_id, scope, target_id, filename, size_bytes, status, note) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $scope, $targetId, $filename, $size, 'available', $note]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function markRestored(int $id, int $userId, string $role): void
    {
        $sql = 'UPDATE backups SET status = ? WHERE id = ?';
        $params = ['restored', $id];
        if ($role !== 'admin') {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id, int $userId, string $role): void
    {
        $sql = 'DELETE FROM backups WHERE id = ?';
        $params = [$id];
        if ($role !== 'admin') {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
    }
}