<?php

declare(strict_types=1);

namespace CloudFS\Repositories;

use CloudFS\Database\Database;

final class FileRepository
{
    public function __construct(private Database $db)
    {
    }

    public function findFile(int $fileId): ?array
    {
        return $this->db->one('SELECT * FROM files WHERE id = ?', [$fileId]);
    }

    public function listForOwner(int $ownerId, int $folderId = 0, string $status = 'active'): array
    {
        if ($folderId <= 0) {
            return $this->db->all(
                'SELECT * FROM files WHERE owner_id = ? AND status = ? ORDER BY name',
                [$ownerId, $status]
            );
        }
        return $this->db->all(
            'SELECT * FROM files WHERE owner_id = ? AND status = ? AND folder_id = ? ORDER BY name',
            [$ownerId, $status, $folderId]
        );
    }

    public function createFile(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO files (owner_id, folder_id, name, original_name, storage_path, mime_type, size_bytes, description, tags, status, current_version, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', 1, datetime(\'now\'), datetime(\'now\'))',
            [
                $data['owner_id'],
                $data['folder_id'] ?? null,
                $data['name'],
                $data['original_name'],
                $data['storage_path'],
                $data['mime_type'],
                $data['size_bytes'],
                $data['description'] ?? '',
                $data['tags'] ?? '',
            ]
        );
    }

    public function addVersion(int $fileId, int $versionNumber, string $storagePath, int $size, string $mime, int $uploadedBy, string $comment): int
    {
        return $this->db->insert(
            'INSERT INTO file_versions (file_id, version_number, storage_path, size_bytes, mime_type, uploaded_by, comment, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$fileId, $versionNumber, $storagePath, $size, $mime, $uploadedBy, $comment]
        );
    }

    public function updateFile(int $fileId, array $fields): void
    {
        $allowed = ['name', 'description', 'tags', 'folder_id', 'current_version', 'storage_path', 'size_bytes', 'mime_type'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $fields)) {
                $sets[] = "$field = ?";
                $params[] = $fields[$field];
            }
        }
        if (!$sets) {
            return;
        }
        $sets[] = 'updated_at = datetime(\'now\')';
        $params[] = $fileId;
        $this->db->run('UPDATE files SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public function versions(int $fileId): array
    {
        return $this->db->all(
            'SELECT v.*, u.username AS uploaded_by_name FROM file_versions v LEFT JOIN users u ON u.id = v.uploaded_by WHERE v.file_id = ? ORDER BY v.version_number DESC',
            [$fileId]
        );
    }

    public function version(int $fileId, int $versionNumber): ?array
    {
        return $this->db->one(
            'SELECT * FROM file_versions WHERE file_id = ? AND version_number = ?',
            [$fileId, $versionNumber]
        );
    }

    public function trashFile(int $fileId, int $originalFolderId, int $userId): void
    {
        $this->db->transaction(function () use ($fileId, $originalFolderId, $userId) {
            $this->db->run(
                "UPDATE files SET status = 'trashed', trashed_at = datetime('now'), deleted_at = datetime('now', '+' || (SELECT value FROM settings WHERE key = 'trash_retention_days') || ' days') WHERE id = ?",
                [$fileId]
            );
            $this->db->insert('INSERT INTO trash (file_id, original_folder_id, trashed_by, trashed_at) VALUES (?, ?, ?, datetime(\'now\'))', [$fileId, $originalFolderId, $userId]);
        });
    }

    public function listTrash(int $userId): array
    {
        return $this->db->all(
            'SELECT t.id AS trash_id, t.file_id, t.trashed_at, t.original_folder_id, f.*
             FROM trash t JOIN files f ON f.id = t.file_id
             WHERE f.owner_id = ? ORDER BY t.trashed_at DESC',
            [$userId]
        );
    }

    public function trashEntry(int $trashId, int $ownerId): ?array
    {
        return $this->db->one(
            'SELECT t.id AS trash_id, t.file_id, t.trashed_at, t.original_folder_id, f.*
             FROM trash t JOIN files f ON f.id = t.file_id
             WHERE t.id = ? AND f.owner_id = ?',
            [$trashId, $ownerId]
        );
    }

    public function restoreFile(int $trashId): void
    {
        $this->db->transaction(function () use ($trashId) {
            $entry = $this->db->one('SELECT * FROM trash WHERE id = ?', [$trashId]);
            if (!$entry) {
                return;
            }
            $this->db->run("UPDATE files SET status = 'active', trashed_at = NULL, deleted_at = NULL WHERE id = ?", [(int) $entry['file_id']]);
            $this->db->run('DELETE FROM trash WHERE id = ?', [$trashId]);
        });
    }

    public function purgeFile(int $trashId, int $ownerId): ?array
    {
        $entry = $this->trashEntry($trashId, $ownerId);
        if (!$entry) {
            return null;
        }
        $this->db->transaction(function () use ($trashId, $entry) {
            $this->db->run('DELETE FROM trash WHERE id = ?', [$trashId]);
            $this->db->run('DELETE FROM files WHERE id = ?', [(int) $entry['file_id']]);
        });
        return $entry;
    }

    public function createFolder(int $ownerId, ?int $parentId, string $name): array
    {
        $exists = $this->db->one('SELECT id FROM folders WHERE owner_id = ? AND parent_id IS ? AND name = ?', [$ownerId, $parentId ?? null, $name]);
        if ($exists) {
            return ['ok' => false, 'errors' => ['A folder with that name already exists in this location.']];
        }
        $id = $this->db->insert(
            'INSERT INTO folders (owner_id, parent_id, name, created_at, updated_at) VALUES (?, ?, ?, datetime(\'now\'), datetime(\'now\'))',
            [$ownerId, $parentId, $name]
        );
        return ['ok' => true, 'id' => $id];
    }

    public function findFolder(int $folderId, int $ownerId): ?array
    {
        return $this->db->one('SELECT * FROM folders WHERE id = ? AND owner_id = ?', [$folderId, $ownerId]);
    }

    public function listFolders(int $ownerId, int $parentId = 0): array
    {
        if ($parentId <= 0) {
            return $this->db->all('SELECT * FROM folders WHERE owner_id = ? AND parent_id IS NULL ORDER BY name', [$ownerId]);
        }
        return $this->db->all('SELECT * FROM folders WHERE owner_id = ? AND parent_id = ? ORDER BY name', [$ownerId, $parentId]);
    }

    public function renameFolder(int $folderId, int $ownerId, string $newName): array
    {
        $folder = $this->findFolder($folderId, $ownerId);
        if (!$folder) {
            return ['ok' => false, 'errors' => ['Folder not found or not owned by you.']];
        }
        $this->db->run('UPDATE folders SET name = ?, updated_at = datetime(\'now\') WHERE id = ?', [$newName, $folderId]);
        return ['ok' => true];
    }

    public function moveFolder(int $folderId, int $ownerId, int $targetParentId): array
    {
        $folder = $this->findFolder($folderId, $ownerId);
        if (!$folder) {
            return ['ok' => false, 'errors' => ['Folder not found or not owned by you.']];
        }
        if ($targetParentId > 0) {
            $target = $this->findFolder($targetParentId, $ownerId);
            if (!$target) {
                return ['ok' => false, 'errors' => ['Target folder not found or not owned by you.']];
            }
            if ($targetParentId === $folderId) {
                return ['ok' => false, 'errors' => ['A folder cannot be moved into itself.']];
            }
        }
        $this->db->run('UPDATE folders SET parent_id = ?, updated_at = datetime(\'now\') WHERE id = ?', [$targetParentId > 0 ? $targetParentId : null, $folderId]);
        return ['ok' => true];
    }

    public function deleteFolder(int $folderId, int $ownerId): array
    {
        $folder = $this->findFolder($folderId, $ownerId);
        if (!$folder) {
            return ['ok' => false, 'errors' => ['Folder not found or not owned by you.']];
        }
        $children = $this->db->value('SELECT COUNT(*) FROM folders WHERE parent_id = ?', [$folderId]);
        $files = $this->db->value('SELECT COUNT(*) FROM files WHERE folder_id = ? AND status = \'active\'', [$folderId]);
        if ($children > 0) {
            return ['ok' => false, 'errors' => ['Folder still contains subfolders; move or delete them first.']];
        }
        if ($files > 0) {
            return ['ok' => false, 'errors' => ['Folder still contains active files; move or trash them first.']];
        }
        $this->db->run('DELETE FROM folders WHERE id = ?', [$folderId]);
        return ['ok' => true];
    }

    public function createShare(int $fileId, int $ownerId, string $scope, string $permissions, ?string $expiresAt, ?string $password): array
    {
        $file = $this->findFile($fileId);
        if (!$file || (int) $file['owner_id'] !== $ownerId) {
            return ['ok' => false, 'errors' => ['File not found or not owned by you.']];
        }
        $token = bin2hex(random_bytes(10));
        $this->db->insert(
            'INSERT INTO shares (file_id, owner_id, token, scope, password_hash, permissions, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$fileId, $ownerId, $token, $scope, $password ? password_hash($password, PASSWORD_DEFAULT) : null, $permissions, $expiresAt]
        );
        return ['ok' => true, 'token' => $token];
    }

    public function listSharesForOwner(int $ownerId): array
    {
        return $this->db->all(
            'SELECT s.*, f.name AS file_name, f.original_name
             FROM shares s JOIN files f ON f.id = s.file_id
             WHERE s.owner_id = ? ORDER BY s.created_at DESC',
            [$ownerId]
        );
    }

    public function findShareByToken(string $token): ?array
    {
        return $this->db->one(
            'SELECT s.*, f.name AS file_name, f.original_name, f.mime_type, f.size_bytes, f.owner_id, f.storage_path
             FROM shares s JOIN files f ON f.id = s.file_id
             WHERE s.token = ? AND s.revoked = 0',
            [$token]
        );
    }

    public function findShare(int $shareId, int $ownerId): ?array
    {
        return $this->db->one('SELECT * FROM shares WHERE id = ? AND owner_id = ?', [$shareId, $ownerId]);
    }

    public function revokeShare(int $shareId, int $ownerId): array
    {
        $share = $this->findShare($shareId, $ownerId);
        if (!$share) {
            return ['ok' => false, 'errors' => ['Share not found or not owned by you.']];
        }
        $this->db->run('UPDATE shares SET revoked = 1 WHERE id = ?', [$shareId]);
        return ['ok' => true];
    }

    public function updateShare(int $shareId, int $ownerId, ?string $permissions, ?string $expiresAt, ?string $scope): array
    {
        $share = $this->findShare($shareId, $ownerId);
        if (!$share) {
            return ['ok' => false, 'errors' => ['Share not found or not owned by you.']];
        }
        $sets = [];
        $params = [];
        if ($permissions !== null) {
            $sets[] = 'permissions = ?';
            $params[] = $permissions;
        }
        if ($expiresAt !== null) {
            $sets[] = 'expires_at = ?';
            $params[] = $expiresAt;
        }
        if ($scope !== null) {
            $sets[] = 'scope = ?';
            $params[] = $scope;
        }
        if ($sets) {
            $params[] = $shareId;
            $this->db->run('UPDATE shares SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
        }
        return ['ok' => true];
    }
}
