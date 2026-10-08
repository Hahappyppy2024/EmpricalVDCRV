<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class BackupRepository extends BaseRepository
{
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM backups WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM backups WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $backup = $stmt->fetch();

        return $backup ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM backups WHERE id = ?');
        $stmt->execute([$id]);
        $backup = $stmt->fetch();

        return $backup ?: null;
    }

    public function create(int $userId, string $name, string $kind, string $sourceType, ?int $sourceId, string $storedPath, int $fileSize): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO backups (user_id, name, kind, source_type, source_id, stored_path, file_size, status, created_at) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $name, $kind, $sourceType, $sourceId, $storedPath, $fileSize, 'ready', date('Y-m-d H:i:s')]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $restoredAt = null): void
    {
        if ($restoredAt !== null) {
            $this->db->prepare('UPDATE backups SET status = ?, restored_at = ? WHERE id = ?')->execute([$status, $restoredAt, $id]);
        } else {
            $this->db->prepare('UPDATE backups SET status = ? WHERE id = ?')->execute([$status, $id]);
        }
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM backups WHERE id = ?')->execute([$id]);
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM backups WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
