<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class FileRepository
{
    public function listForUser(int $userId, ?int $siteId = null): array
    {
        if ($siteId) {
            $stmt = Database::pdo()->prepare('SELECT * FROM files WHERE user_id = ? AND site_id = ? ORDER BY path');
            $stmt->execute([$userId, $siteId]);
        } else {
            $stmt = Database::pdo()->prepare('SELECT * FROM files WHERE user_id = ? ORDER BY path');
            $stmt->execute([$userId]);
        }
        return $stmt->fetchAll();
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function existsAt(int $userId, string $path): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM files WHERE user_id = ? AND path = ?');
        $stmt->execute([$userId, $path]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(int $userId, ?int $siteId, string $parent, string $name, string $path, int $size, string $mime): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO files (user_id, site_id, parent_path, name, path, size_bytes, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $siteId, $parent, $name, $path, $size, $mime]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function rename(int $id, int $userId, string $newName, string $newPath): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE files SET name = ?, path = ?, updated_at = datetime("now") WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$newName, $newPath, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}