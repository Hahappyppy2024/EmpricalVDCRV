<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class SettingsRepository
{
    public function all(): array
    {
        $rows = Database::pdo()->query('SELECT key, value FROM settings')->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[$r['key']] = $r['value'];
        return $out;
    }

    public function set(string $key, string $value): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO settings (key, value, updated_at) VALUES (?, ?, datetime("now"))
                                          ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime("now")');
        $stmt->execute([$key, $value]);
    }
}