<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\BackupRepository;
use App\Repository\SiteRepository;

final class BackupService
{
    private BackupRepository $backups;

    private SiteRepository $sites;

    private AuditRepository $audit;

    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->backups = new BackupRepository();
        $this->sites = new SiteRepository();
        $this->audit = new AuditRepository();
        $this->storageDir = rtrim($storageDir ?? \App\Support\Storage::path(), '/\\');
    }

    public function listFor(array $user): array
    {
        return $this->backups->allForUser((int) $user['id']);
    }

    public function listForActor(array $user): array
    {
        if ($user['role'] === 'admin') {
            return \App\Database\Connection::db()->query('SELECT * FROM backups ORDER BY id DESC')->fetchAll() ?: [];
        }

        return $this->listFor($user);
    }

    public function getForActor(array $user, int $id): ?array
    {
        if ($user['role'] === 'admin') {
            return $this->backups->findById($id);
        }

        return $this->backups->findForUser($id, (int) $user['id']);
    }

    public function restoreForActor(array $user, int $id): ?string
    {
        $backup = $this->getForActor($user, $id);
        if ($backup === null) {
            return 'Unknown or out-of-scope backup.';
        }

        return $this->restore(['id' => (int) $backup['user_id'], 'username' => $user['username']], $id);
    }

    public function deleteForActor(array $user, int $id): ?string
    {
        $backup = $this->getForActor($user, $id);
        if ($backup === null) {
            return 'Unknown or out-of-scope backup.';
        }

        return $this->delete(['id' => (int) $backup['user_id'], 'username' => $user['username']], $id);
    }

    public function sitesFor(array $user): array
    {
        return $this->sites->allForUser((int) $user['id']);
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, backupId]
     */
    public function create(array $user, string $name, string $sourceType, ?int $sourceId): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['Backup name is required.', null];
        }
        if (!in_array($sourceType, ['site', 'database', 'full'], true)) {
            return ['Invalid backup source type.', null];
        }

        if ($sourceType === 'site') {
            $site = $sourceId !== null ? $this->sites->findForUser($sourceId, (int) $user['id']) : null;
            if ($site === null) {
                return ['Selected site does not exist for your account.', null];
            }
        }

        $dir = $this->backupDir((int) $user['id']);
        $fileName = $this->safeFileName($name) . '_' . date('YmdHis') . '.backup';
        $target = $dir . '/' . $fileName;

        $payload = [
            'name' => $name,
            'kind' => $sourceType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'user_id' => (int) $user['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'content' => 'Deterministic local backup snapshot for ' . $name,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($target, $json);

        $id = $this->backups->create((int) $user['id'], $name, $sourceType, $sourceType, $sourceId, $target, strlen($json));
        $this->audit->record((int) $user['id'], $user['username'], 'create', 'backup_and_restore', 'backup', (string) $id, 'Created backup ' . $name);

        return [null, $id];
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, backupId]
     */
    public function upload(array $user, array $uploaded): array
    {
        if (!isset($uploaded['tmp_name']) || $uploaded['error'] !== UPLOAD_ERR_OK) {
            return ['Upload failed or file is missing.', null];
        }
        $fileSize = (int) $uploaded['size'];
        if ($fileSize > 32 * 1024 * 1024) {
            return ['Backup file exceeds the 32 MB upload limit.', null];
        }
        $fileName = basename((string) $uploaded['name']);
        if (!preg_match('/\.(backup|zip|tar|gz|sql)$/i', $fileName)) {
            return ['Invalid backup file type.', null];
        }

        $dir = $this->backupDir((int) $user['id']);
        $target = $dir . '/' . $this->safeFileName($fileName);
        if (!move_uploaded_file($uploaded['tmp_name'], $target)) {
            return ['Could not store the uploaded backup.', null];
        }

        $id = $this->backups->create((int) $user['id'], pathinfo($fileName, PATHINFO_FILENAME), 'full', 'upload', null, $target, $fileSize);
        $this->audit->record((int) $user['id'], $user['username'], 'upload', 'backup_and_restore', 'backup', (string) $id, 'Uploaded backup ' . $fileName);

        return [null, $id];
    }

    /**
     * @return array{0: ?string, 1: ?array} [error, [path, name, size]]
     */
    public function download(array $user, int $id): array
    {
        $backup = $this->backups->findForUser($id, (int) $user['id']);
        if ($backup === null) {
            return ['Unknown or out-of-scope backup.', null];
        }
        if (!is_file($backup['stored_path'])) {
            return ['Backup file is missing on disk.', null];
        }

        return [null, ['path' => $backup['stored_path'], 'name' => basename($backup['stored_path']), 'size' => (int) $backup['file_size']]];
    }

    public function restore(array $user, int $id): ?string
    {
        $backup = $this->backups->findForUser($id, (int) $user['id']);
        if ($backup === null) {
            return 'Unknown or out-of-scope backup.';
        }
        if (!is_file($backup['stored_path'])) {
            return 'Backup file is missing on disk.';
        }

        $this->backups->updateStatus($id, 'restoring');
        $logDir = $this->storageDir . '/restore_logs/' . $user['id'];
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents(
            $logDir . '/restore_' . $id . '.log',
            "Restore of {$backup['name']} started at " . date('Y-m-d H:i:s') . "\n"
        );
        $this->backups->updateStatus($id, 'restored', date('Y-m-d H:i:s'));
        $this->audit->record((int) $user['id'], $user['username'], 'restore', 'backup_and_restore', 'backup', (string) $id, 'Restored backup ' . $backup['name']);

        return null;
    }

    public function delete(array $user, int $id): ?string
    {
        $backup = $this->backups->findForUser($id, (int) $user['id']);
        if ($backup === null) {
            return 'Unknown or out-of-scope backup.';
        }
        if (is_file($backup['stored_path'])) {
            unlink($backup['stored_path']);
        }
        $this->backups->delete($id);
        $this->audit->record((int) $user['id'], $user['username'], 'delete', 'backup_and_restore', 'backup', (string) $id, 'Deleted backup ' . $backup['name']);

        return null;
    }

    private function backupDir(int $userId): string
    {
        $dir = $this->storageDir . '/backups/' . $userId;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private function safeFileName(string $name): string
    {
        $name = str_replace(['..', '/', '\\', ':'], '_', $name);

        return preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'backup';
    }
}
