<?php
declare(strict_types=1);

namespace App;

use PDO;

final class CloudRepository
{
    use Support;

    public function __construct(private PDO $db, private string $uploadDir)
    {
    }

    public function folder(int $id): array
    {
        return self::one($this->db, 'SELECT * FROM folders WHERE id=?', [$id], 'folder_not_found');
    }

    public function file(int $id): array
    {
        return self::one($this->db, 'SELECT * FROM files WHERE id=?', [$id], 'file_not_found');
    }

    public function canReadFolder(array $user, array $folder): bool
    {
        if ($user['role'] === 'admin' || (int)$folder['owner_id'] === (int)$user['id']) {
            return true;
        }
        if ($folder['team_space_id'] === null) {
            return false;
        }
        $statement = $this->db->prepare('SELECT 1 FROM team_members WHERE space_id=? AND user_id=?');
        $statement->execute([$folder['team_space_id'], $user['id']]);
        return (bool)$statement->fetchColumn();
    }

    public function requireFolder(array $user, int $id, bool $write = false): array
    {
        $folder = $this->folder($id);
        if (!$this->canReadFolder($user, $folder)) {
            throw new ApiException(403, 'folder_access_denied', 'Folder access is denied.');
        }
        if ($write && $folder['team_space_id'] !== null && $user['role'] !== 'admin') {
            $statement = $this->db->prepare("SELECT role FROM team_members WHERE space_id=? AND user_id=?");
            $statement->execute([$folder['team_space_id'], $user['id']]);
            if (!$statement->fetchColumn()) {
                throw new ApiException(403, 'folder_write_denied', 'Folder write access is denied.');
            }
        }
        if ($folder['trashed_at'] !== null) {
            throw new ApiException(409, 'folder_trashed', 'The folder is in trash.');
        }
        return $folder;
    }

    public function requireFile(array $user, int $id, bool $write = false): array
    {
        $file = $this->file($id);
        $this->requireFolder($user, (int)$file['folder_id'], $write);
        if ($file['trashed_at'] !== null) {
            throw new ApiException(409, 'file_trashed', 'The file is in trash.');
        }
        return $file;
    }

    public function requireTeamAdmin(array $user, int $spaceId): array
    {
        $space = self::one($this->db, 'SELECT * FROM team_spaces WHERE id=?', [$spaceId], 'team_space_not_found');
        if ($user['role'] === 'admin') {
            return $space;
        }
        $statement = $this->db->prepare("SELECT role FROM team_members WHERE space_id=? AND user_id=?");
        $statement->execute([$spaceId, $user['id']]);
        if ($statement->fetchColumn() !== 'team_admin') {
            throw new ApiException(403, 'team_admin_required', 'Team administrator access is required.');
        }
        return $space;
    }

    public function quota(int $userId): array
    {
        $user = self::one($this->db, 'SELECT id,quota_bytes FROM users WHERE id=?', [$userId], 'user_not_found');
        $usage = self::one($this->db, 'SELECT COALESCE(SUM(b.size_bytes),0) used FROM file_versions v JOIN stored_blobs b ON b.id=v.blob_id JOIN files f ON f.id=v.file_id WHERE f.owner_id=?', [$userId]);
        $used = (int)$usage['used'];
        return ['userId' => (int)$user['id'], 'usedBytes' => $used, 'quotaBytes' => (int)$user['quota_bytes'], 'remainingBytes' => max(0, (int)$user['quota_bytes'] - $used)];
    }

    public function ensureQuota(int $userId, int $newBytes): void
    {
        $quota = $this->quota($userId);
        if ($quota['usedBytes'] + $newBytes > $quota['quotaBytes']) {
            throw new ApiException(409, 'quota_exceeded', 'The upload exceeds the storage quota.');
        }
    }

    public function storeBlob(string $contents): array
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }
        $stored = bin2hex(random_bytes(16)) . '.blob';
        file_put_contents($this->uploadDir . '/' . $stored, $contents);
        $this->db->prepare('INSERT INTO stored_blobs(stored_name,size_bytes,checksum) VALUES(?,?,?)')
            ->execute([$stored, strlen($contents), hash('sha256', $contents)]);
        return self::one($this->db, 'SELECT * FROM stored_blobs WHERE id=?', [(int)$this->db->lastInsertId()]);
    }

    public function blobContent(int $blobId): array
    {
        $blob = self::one($this->db, 'SELECT * FROM stored_blobs WHERE id=?', [$blobId], 'blob_not_found');
        $path = $this->uploadDir . '/' . $blob['stored_name'];
        if (!is_file($path)) {
            throw new ApiException(404, 'content_not_found', 'Stored content is unavailable.');
        }
        return [$blob, (string)file_get_contents($path)];
    }

    public function discardBlobFile(array $blob): void
    {
        $path = $this->uploadDir . '/' . $blob['stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function audit(?int $spaceId, int $actorId, string $action, string $entityType, int $entityId, string $details = ''): void
    {
        $this->db->prepare('INSERT INTO audit_events(team_space_id,actor_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?,?)')
            ->execute([$spaceId, $actorId, $action, $entityType, $entityId, $details]);
    }
}
