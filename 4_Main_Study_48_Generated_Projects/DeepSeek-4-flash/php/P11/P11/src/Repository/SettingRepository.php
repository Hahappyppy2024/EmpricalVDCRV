<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class SettingRepository extends BaseRepository
{
    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?,?)');
        $stmt->execute([$key, $value]);
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM settings ORDER BY key')->fetchAll();
    }
}
