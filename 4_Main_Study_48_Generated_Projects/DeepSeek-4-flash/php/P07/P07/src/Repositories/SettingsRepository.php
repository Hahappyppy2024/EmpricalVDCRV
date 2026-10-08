<?php

declare(strict_types=1);

namespace CloudFS\Repositories;

use CloudFS\Database\Database;

final class SettingsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        $value = $this->db->value('SELECT value FROM settings WHERE key = ?', [$key]);
        return $value === false || $value === null ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $this->db->run(
            'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [$key, $value]
        );
    }

    public function all(): array
    {
        $rows = $this->db->all('SELECT * FROM settings ORDER BY key');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    }

    public function blockedExtensions(): array
    {
        $rows = $this->db->all('SELECT extension, reason FROM blocked_file_types ORDER BY extension');
        $out = [];
        foreach ($rows as $row) {
            $out[strtolower($row['extension'])] = $row['reason'];
        }
        return $out;
    }

    public function addBlockedExtension(string $extension, string $reason, int $byUserId): array
    {
        $ext = strtolower(trim($extension, " .\t\n\r\0\x0B"));
        if ($ext === '') {
            return ['ok' => false, 'errors' => ['Extension is required.']];
        }
        $this->db->insert(
            'INSERT OR IGNORE INTO blocked_file_types (extension, reason, created_by, created_at) VALUES (?, ?, ?, datetime(\'now\'))',
            [$ext, $reason ?: 'blocked by admin policy', $byUserId]
        );
        return ['ok' => true];
    }

    public function removeBlockedExtension(string $extension): void
    {
        $this->db->run('DELETE FROM blocked_file_types WHERE extension = ?', [strtolower(trim($extension, " .\t\n\r\0\x0B"))]);
    }
}
