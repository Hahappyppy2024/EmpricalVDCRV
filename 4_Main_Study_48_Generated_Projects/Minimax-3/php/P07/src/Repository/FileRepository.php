<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class FileRepository
{
    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM stored_files WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByStorage(string $storageName): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM stored_files WHERE storage_name = ?');
        $stmt->execute([$storageName]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO stored_files (folder_id, owner_id, team_id, original_name, storage_name, description, mime_type, size, checksum, purpose, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['folder_id'] ?? null,
            $data['owner_id'] ?? null,
            $data['team_id'] ?? null,
            $data['original_name'],
            $data['storage_name'],
            $data['description'] ?? null,
            $data['mime_type'] ?? 'application/octet-stream',
            $data['size'] ?? 0,
            $data['checksum'] ?? null,
            $data['purpose'] ?? 'user_file',
            $data['status'] ?? 'active',
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function updateMetadata(int $id, array $fields): void
    {
        $allowed = ['description', 'original_name', 'folder_id'];
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
        Database::pdo()->prepare('UPDATE stored_files SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    public function setStatus(int $id, string $status): void
    {
        Database::pdo()->prepare('UPDATE stored_files SET status = ?, deleted_at = CASE WHEN ? = \'trashed\' THEN datetime(\'now\') ELSE NULL END, updated_at = datetime(\'now\') WHERE id = ?')
            ->execute([$status, $status, $id]);
    }

    public function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM stored_files WHERE id = ?')->execute([$id]);
    }

    public function listInFolder(int $folderId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM stored_files WHERE folder_id = ? AND status = \'active\' AND purpose = \'user_file\' ORDER BY original_name');
        $stmt->execute([$folderId]);
        return $stmt->fetchAll();
    }

    public function listForOwner(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM stored_files WHERE owner_id = ? AND status = \'active\' AND purpose = \'user_file\' ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function listForTeam(int $teamId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM stored_files WHERE team_id = ? AND status = \'active\' AND purpose = \'user_file\' ORDER BY created_at DESC');
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function listSharedWith(int $userId, array $teamIds): array
    {
        if ($teamIds === []) {
            $stmt = Database::pdo()->prepare('SELECT * FROM stored_files WHERE owner_id = ? AND status = \'active\' AND purpose = \'user_file\' ORDER BY created_at DESC');
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        }
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $sql = "SELECT * FROM stored_files WHERE (owner_id = ? OR team_id IN ($placeholders)) AND status = 'active' AND purpose = 'user_file' ORDER BY created_at DESC";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(array_merge([$userId], $teamIds));
        return $stmt->fetchAll();
    }

    public function search(array $filters, array $teamIds): array
    {
        $where = ["f.status = 'active'", "f.purpose = 'user_file'"];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = 'f.original_name LIKE ?';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['owner'])) {
            $where[] = 'u.email = ?';
            $params[] = $filters['owner'];
        }
        if (!empty($filters['tag'])) {
            $where[] = 'ft.tag = ?';
            $params[] = $filters['tag'];
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'f.created_at >= ?';
            $params[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'f.created_at <= ?';
            $params[] = $filters['to_date'];
        }
        $teamClause = '';
        if ($teamIds !== []) {
            $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
            $teamClause = " OR (f.team_id IN ($placeholders))";
            $params = array_merge($params, $teamIds);
        }
        $where[] = '(f.owner_id = ?' . $teamClause . ')';
        $params[] = $filters['owner_id'];
        $sql = "SELECT f.*, u.email AS owner_email FROM stored_files f LEFT JOIN users u ON u.id = f.owner_id LEFT JOIN file_tags ft ON ft.file_id = f.id WHERE " . implode(' AND ', $where) . " GROUP BY f.id ORDER BY f.created_at DESC LIMIT 200";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function setTags(int $fileId, array $tags): void
    {
        Database::pdo()->prepare('DELETE FROM file_tags WHERE file_id = ?')->execute([$fileId]);
        $stmt = Database::pdo()->prepare('INSERT OR IGNORE INTO file_tags (file_id, tag) VALUES (?, ?)');
        foreach ($tags as $tag) {
            $stmt->execute([$fileId, $tag]);
        }
    }

    public function getTags(int $fileId): array
    {
        $stmt = Database::pdo()->prepare('SELECT tag FROM file_tags WHERE file_id = ?');
        $stmt->execute([$fileId]);
        return array_column($stmt->fetchAll(), 'tag');
    }

    public function recordUpload(int $fileId, int $userId, ?int $folderId, string $originalName, ?string $description, array $tags, int $size, string $status = 'completed'): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO file_uploads (file_id, uploaded_by, folder_id, original_name, description, tags, size, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $fileId,
            $userId,
            $folderId,
            $originalName,
            $description,
            implode(',', $tags),
            $size,
            $status,
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function versions(int $fileId): array
    {
        $stmt = Database::pdo()->prepare('SELECT v.*, sf.storage_name FROM versions v JOIN stored_files sf ON sf.id = v.stored_file_id WHERE v.file_id = ? ORDER BY v.version_number DESC');
        $stmt->execute([$fileId]);
        return $stmt->fetchAll();
    }

    public function createVersion(int $fileId, int $versionNumber, int $storedFileId, int $uploadedBy, int $size, ?string $summary): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO versions (file_id, version_number, stored_file_id, uploaded_by, size, change_summary) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$fileId, $versionNumber, $storedFileId, $uploadedBy, $size, $summary]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function nextVersionNumber(int $fileId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(MAX(version_number), 0) AS max_version FROM versions WHERE file_id = ?');
        $stmt->execute([$fileId]);
        $row = $stmt->fetch();
        return ((int)$row['max_version']) + 1;
    }

    public function recordDownload(int $fileId, ?int $userId, string $accessMethod, string $action, bool $success): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO file_download_log (file_id, user_id, access_method, action, success) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$fileId, $userId, $accessMethod, $action, $success ? 1 : 0]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function maxStorageName(): int
    {
        $stmt = Database::pdo()->query('SELECT COUNT(*) AS c FROM stored_files');
        $row = $stmt->fetch();
        return (int)$row['c'];
    }
}