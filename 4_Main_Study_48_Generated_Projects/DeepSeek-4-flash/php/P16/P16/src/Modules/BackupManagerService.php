<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-07 Backup manager.
 *
 * Persistent entities: User, Session, BackupManager, StoredFile.
 * Users create, download, upload, restore and delete backups. Every stored
 * file keeps metadata that links it to the owning user.
 */
final class BackupManagerService extends BaseService
{
    public const TYPES = ['manual', 'scheduled'];
    public const STATUSES = ['available', 'restored', 'deleted'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT b.*, sf.original_name, sf.filename, sf.mime_type, sf.sha256, sf.disk_path,
                       u.username AS owner_name
                FROM backups b
                LEFT JOIN stored_files sf ON sf.id = b.file_id
                LEFT JOIN users u ON u.id = b.owner_id WHERE b.status != \'deleted\'';
        $params = [];
        if (($user['role'] ?? '') !== 'admin') {
            $sql .= ' AND b.owner_id = :uid';
            $params['uid'] = (int) $user['id'];
        }
        if (!empty($filters['backup_type'])) {
            $sql .= ' AND b.backup_type = :type';
            $params['type'] = $filters['backup_type'];
        }
        $sql .= ' ORDER BY b.id DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        $row = $this->db->fetchOne(
            'SELECT b.*, sf.original_name, sf.filename, sf.mime_type, sf.sha256, sf.disk_path,
                    u.username AS owner_name
             FROM backups b
             LEFT JOIN stored_files sf ON sf.id = b.file_id
             LEFT JOIN users u ON u.id = b.owner_id WHERE b.id = ?',
            [$id]
        );
        if ($row === null) {
            throw new \App\NotFoundException('Backup not found.');
        }
        $this->assertOwned($row, $user);

        return $row;
    }

    public function create(array $data, array $user): array
    {
        (new Validator())
            ->required($data, 'name')
            ->string(['name' => $data['name'] ?? ''], 'name', 120, true)
            ->oneOf($data, 'backup_type', self::TYPES, false)
            ->throwIfInvalid();

        $name = trim((string) $data['name']);
        $type = (string) ($data['backup_type'] ?? 'manual');
        // Deterministic local backup artifact: manifest of current config + metrics.
        $manifest = $this->buildManifest();
        $originalName = $this->safeName($name) . '.backup';
        $fileName = bin2hex(random_bytes(16)) . '.backup';
        $dir = $this->config->get('backup.dir');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $diskPath = $dir . '/' . $fileName;
        file_put_contents($diskPath, $manifest);

        $fileId = $this->db->insert('stored_files', [
            'owner_id' => (int) $user['id'],
            'filename' => $fileName,
            'original_name' => $originalName,
            'disk_path' => $diskPath,
            'size_bytes' => strlen($manifest),
            'mime_type' => 'application/octet-stream',
            'sha256' => hash('sha256', $manifest),
            'created_at' => $this->db->now(),
        ]);
        $id = $this->db->insert('backups', [
            'name' => $name,
            'file_id' => $fileId,
            'owner_id' => (int) $user['id'],
            'backup_type' => $type,
            'status' => 'available',
            'size_bytes' => strlen($manifest),
            'created_at' => $this->db->now(),
        ]);
        $this->log($user, 'backup_create', 'backup_manager', 'Backup "' . $name . '" created (' . strlen($manifest) . ' bytes).');

        return $this->item($id, $user);
    }

    public function upload(array $data, array $user): array
    {
        $file = $data['_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \App\ValidationException('A backup file must be uploaded.');
        }
        $maxBytes = 5 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new \App\ValidationException('Uploaded file exceeds the 5 MB limit.');
        }
        if (isset($file['_content']) && $file['_content'] !== '') {
            $content = (string) $file['_content'];
        } else {
            $tmp = (string) ($file['tmp_name'] ?? '');
            $content = is_file($tmp) ? (string) file_get_contents($tmp) : '';
            if ($content === '' && ($file['size'] ?? 0) > 0) {
                throw new \App\ValidationException('Uploaded file could not be read.');
            }
        }

        $originalName = basename((string) ($file['name'] ?? 'backup.backup'));
        if (!preg_match('/^[^\\/:*?"<>|]{1,120}$/', $originalName)) {
            $originalName = 'backup.backup';
        }
        $name = trim((string) ($data['name'] ?? $originalName));
        (new Validator())
            ->required(['name' => $name], 'name')
            ->string(['name' => $name], 'name', 120, true)
            ->throwIfInvalid();

        $fileName = bin2hex(random_bytes(16)) . '.backup';
        $dir = $this->config->get('upload.dir');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $diskPath = $dir . '/' . $fileName;
        file_put_contents($diskPath, $content);

        $fileId = $this->db->insert('stored_files', [
            'owner_id' => (int) $user['id'],
            'filename' => $fileName,
            'original_name' => $originalName,
            'disk_path' => $diskPath,
            'size_bytes' => strlen($content),
            'mime_type' => 'application/octet-stream',
            'sha256' => hash('sha256', $content),
            'created_at' => $this->db->now(),
        ]);
        $id = $this->db->insert('backups', [
            'name' => $name,
            'file_id' => $fileId,
            'owner_id' => (int) $user['id'],
            'backup_type' => 'manual',
            'status' => 'available',
            'size_bytes' => strlen($content),
            'created_at' => $this->db->now(),
        ]);
        $this->log($user, 'backup_upload', 'backup_manager', 'Backup "' . $name . '" uploaded (' . strlen($content) . ' bytes).');

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('backups', $id);
        $this->assertOwned($row, $user);

        $updates = [];
        if (array_key_exists('name', $data)) {
            (new Validator())->string(['name' => $data['name']], 'name', 120, true)->throwIfInvalid();
            $updates['name'] = trim((string) $data['name']);
        }
        if ($updates !== []) {
            $this->db->update('backups', $updates, ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    public function delete(int $id, array $user): void
    {
        $row = $this->requireRow('backups', $id);
        $this->assertOwned($row, $user);

        $file = null;
        if ($row['file_id'] !== null) {
            $file = $this->db->fetchOne('SELECT * FROM stored_files WHERE id = ?', [(int) $row['file_id']]);
        }
        $this->db->update('backups', ['status' => 'deleted'], ['id' => $id]);
        if ($file !== null && is_file((string) $file['disk_path'])) {
            @unlink((string) $file['disk_path']);
        }
        if ($file !== null) {
            $this->db->delete('stored_files', ['id' => (int) $file['id']]);
        }
        $this->log($user, 'backup_delete', 'backup_manager', 'Backup "' . $row['name'] . '" deleted.');
    }

    /** @return array<string, mixed> */
    public function downloadPayload(int $id, array $user): array
    {
        $row = $this->item($id, $user);
        if ($row['disk_path'] === null || !is_file((string) $row['disk_path'])) {
            throw new \App\NotFoundException('Backup file is no longer available.');
        }

        return [
            'content' => (string) file_get_contents((string) $row['disk_path']),
            'filename' => (string) ($row['original_name'] ?: 'backup.backup'),
            'mime' => (string) ($row['mime_type'] ?: 'application/octet-stream'),
        ];
    }

    public function restore(int $id, array $user): array
    {
        $row = $this->requireRow('backups', $id);
        $this->assertOwned($row, $user);
        if ($row['status'] === 'deleted') {
            throw new \App\ValidationException('A deleted backup cannot be restored.');
        }
        $this->db->update('backups', [
            'status' => 'restored',
            'restored_at' => $this->db->now(),
        ], ['id' => $id]);
        $this->log($user, 'backup_restore', 'backup_manager', 'Backup "' . $row['name'] . '" restored.');

        return $this->item($id, $user);
    }

    /** @return array<string, mixed> */
    private function buildManifest(): string
    {
        $config = $this->db->fetchAll('SELECT config_key, config_value, status FROM config_keys ORDER BY config_key');
        $metrics = $this->db->fetchAll('SELECT server_name, cpu_pct, memory_pct, disk_pct, service_status FROM server_dashboard ORDER BY id DESC LIMIT 10');
        $jobs = $this->db->fetchAll('SELECT name, profile, status FROM jobs ORDER BY id');

        return json_encode(
            [
                'format' => 'p16-backup-v1',
                'created_at' => $this->db->now(),
                'config' => $config,
                'latest_metrics' => $metrics,
                'jobs' => $jobs,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($name)) ?: 'backup';
    }

    /** @param array<string, mixed> $row */
    private function assertOwned(array $row, array $user): void
    {
        if (($user['role'] ?? '') === 'admin') {
            return;
        }
        if ((int) ($row['owner_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            throw new \App\NotFoundException('Backup not found.');
        }
    }
}
