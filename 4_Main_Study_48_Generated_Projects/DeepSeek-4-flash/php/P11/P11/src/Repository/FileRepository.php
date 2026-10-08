<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class FileRepository extends BaseRepository
{
    public function listForUser(int $userId, ?string $prefix = null): array
    {
        $sql = 'SELECT * FROM stored_files WHERE user_id = ?';
        $params = [$userId];
        if ($prefix !== null) {
            $sql .= ' AND file_path LIKE ?';
            $params[] = $prefix . '%';
        }
        $sql .= ' ORDER BY is_dir DESC, file_name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM stored_files WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $file = $stmt->fetch();

        return $file ?: null;
    }

    public function findByPath(int $userId, string $path): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM stored_files WHERE user_id = ? AND file_path = ?');
        $stmt->execute([$userId, $path]);
        $file = $stmt->fetch();

        return $file ?: null;
    }

    public function createMeta(int $userId, ?int $siteId, string $path, string $fileName, int $size, string $mime, int $isDir): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'INSERT INTO stored_files (user_id, site_id, file_path, file_name, file_size, mime_type, is_dir, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $siteId, $path, $fileName, $size, $mime, $isDir, $now, $now]);
    }

    public function updateMeta(int $id, ?string $path = null, ?string $fileName = null, ?int $size = null, ?string $mime = null): void
    {
        $sets = ['updated_at = ?'];
        $values = [date('Y-m-d H:i:s')];
        foreach (['path' => $path, 'file_name' => $fileName, 'file_size' => $size, 'mime_type' => $mime] as $col => $value) {
            if ($value !== null) {
                $sets[] = ($col === 'path' ? 'file_path' : $col) . ' = ?';
                $values[] = $value;
            }
        }
        $values[] = $id;
        $this->db->prepare('UPDATE stored_files SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    }

    public function deleteMeta(int $id): void
    {
        $this->db->prepare('DELETE FROM stored_files WHERE id = ?')->execute([$id]);
    }

    public function deleteMetaForPathPrefix(int $userId, string $prefix): void
    {
        $stmt = $this->db->prepare('DELETE FROM stored_files WHERE user_id = ? AND (file_path = ? OR file_path LIKE ?)');
        $stmt->execute([$userId, $prefix, $prefix . '/%']);
    }

    public function updatePathsForRename(int $userId, string $oldPrefix, string $newPrefix): void
    {
        $files = $this->listForUser($userId, $oldPrefix . '/');
        foreach ($files as $file) {
            $newPath = $newPrefix . '/' . substr($file['file_path'], strlen($oldPrefix) + 1);
            $this->updateMeta((int) $file['id'], $newPath, basename($newPath));
        }
    }

    public function totalSizeForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(file_size), 0) FROM stored_files WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
