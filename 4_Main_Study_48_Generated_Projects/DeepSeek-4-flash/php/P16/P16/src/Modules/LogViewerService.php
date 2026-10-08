<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-03 Log viewer.
 *
 * Persistent entities: User, Session, LogViewer (log entries, mirrored to
 * deterministic files under storage/logs for preview and download).
 */
final class LogViewerService extends BaseService
{
    public const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT le.* FROM log_entries le WHERE 1 = 1';
        $params = [];
        if (!empty($filters['file_name'])) {
            $sql .= ' AND le.file_name = :file';
            $params['file'] = $filters['file_name'];
        }
        if (!empty($filters['level'])) {
            $sql .= ' AND le.level = :level';
            $params['level'] = $filters['level'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (le.message LIKE :q OR le.source LIKE :q OR le.file_name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        $limit = min((int) ($filters['limit'] ?? 200), 1000);
        $sql .= ' ORDER BY le.id DESC LIMIT ' . $limit;

        return $this->db->fetchAll($sql, $params);
    }

    public function files(): array
    {
        return $this->db->fetchAll(
            'SELECT file_name, COUNT(*) AS entry_count, MAX(created_at) AS last_entry
             FROM log_entries GROUP BY file_name ORDER BY file_name ASC'
        );
    }

    /** Build the mirrored log file content for one log file. */
    public function fileContent(string $fileName): string
    {
        $this->assertFileName($fileName);
        $rows = $this->db->fetchAll(
            'SELECT * FROM log_entries WHERE file_name = ? ORDER BY id ASC',
            [$fileName]
        );
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '%s [%s] %s: %s',
                $row['created_at'],
                strtoupper((string) $row['level']),
                $row['source'],
                $row['message']
            );
        }

        return implode(PHP_EOL, $lines) . (($lines !== []) ? PHP_EOL : '');
    }

    public function item(int $id, array $user): array
    {
        return $this->requireRow('log_entries', $id);
    }

    public function create(array $data, array $user): array
    {
        (new Validator())
            ->required($data, 'file_name', 'message')
            ->string(['file_name' => $data['file_name'] ?? ''], 'file_name', 100, true)
            ->string(['source' => $data['source'] ?? 'system'], 'source', 100)
            ->oneOf($data, 'level', self::LEVELS, true)
            ->string(['message' => $data['message'] ?? ''], 'message', 2000, true)
            ->throwIfInvalid();

        $fileName = $this->normalizeFileName((string) $data['file_name']);
        $id = $this->db->insert('log_entries', [
            'file_name' => $fileName,
            'level' => $data['level'],
            'source' => trim((string) ($data['source'] ?? 'system')),
            'message' => trim((string) $data['message']),
            'created_at' => $this->db->now(),
        ]);
        $this->syncFile($fileName);

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('log_entries', $id);
        $validator = (new Validator())->oneOf($data, 'level', self::LEVELS);
        if (array_key_exists('message', $data)) {
            $validator->string(['message' => $data['message']], 'message', 2000, true);
        }
        if (array_key_exists('source', $data)) {
            $validator->string(['source' => $data['source']], 'source', 100);
        }
        if (array_key_exists('file_name', $data)) {
            $validator->string(['file_name' => $data['file_name']], 'file_name', 100, true);
        }
        $validator->throwIfInvalid();

        $updates = [];
        foreach (['level', 'source', 'file_name', 'message'] as $f) {
            if (array_key_exists($f, $data)) {
                $updates[$f] = $f === 'file_name' ? $this->normalizeFileName((string) $data[$f]) : trim((string) $data[$f]);
            }
        }
        if ($updates !== []) {
            $this->db->update('log_entries', $updates, ['id' => $id]);
        }
        // Mirror the previous file as well when the file_name changed.
        $this->syncFile($row['file_name']);
        if (isset($updates['file_name']) && $updates['file_name'] !== $row['file_name']) {
            $this->syncFile($updates['file_name']);
        }

        return $this->item($id, $user);
    }

    public function delete(int $id, array $user): void
    {
        $row = $this->requireRow('log_entries', $id);
        $this->db->delete('log_entries', ['id' => $id]);
        $this->syncFile($row['file_name']);
    }

    public function mirrorAll(): void
    {
        foreach ($this->files() as $file) {
            $this->syncFile($file['file_name']);
        }
    }

    public function mirrorSeed(): void
    {
        $this->mirrorAll();
    }

    private function syncFile(string $fileName): void
    {
        $this->assertFileName($fileName);
        $dir = $this->config->get('log.dir');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/' . $fileName, $this->fileContent($fileName));
    }

    private function normalizeFileName(string $fileName): string
    {
        $fileName = basename(trim($fileName));
        if ($fileName === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $fileName)) {
            throw new \App\ValidationException('Invalid log file name.');
        }

        return $fileName;
    }

    private function assertFileName(string $fileName): void
    {
        $this->normalizeFileName($fileName);
    }
}
