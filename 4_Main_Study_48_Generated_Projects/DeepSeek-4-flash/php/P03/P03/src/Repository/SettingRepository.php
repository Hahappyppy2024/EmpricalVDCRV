<?php

declare(strict_types=1);

namespace Shop\Repository;

final class SettingRepository extends Repository
{
    /**
     * @return array<string,string>
     */
    public function all(): array
    {
        $map = [];
        foreach ($this->rows('SELECT * FROM settings ORDER BY id') as $row) {
            $map[$row['key']] = $row['value'];
        }
        return $map;
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->row('SELECT value FROM settings WHERE key = ?', [$key]);
        return $row['value'] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $this->exec(
            'INSERT INTO settings (key, value, updated_at) VALUES (?, ?, datetime(\'now\'))
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime(\'now\')',
            [$key, $value]
        );
    }
}
