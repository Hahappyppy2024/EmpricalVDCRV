<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;

final class SettingsRepository
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT value FROM settings WHERE key = :k');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['value'] : $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::pdo()->prepare(
            'INSERT INTO settings (key, value) VALUES (:k, :v)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime("now")'
        )->execute([':k' => $key, ':v' => $value]);
    }

    public static function all(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM settings ORDER BY key ASC')
            ->fetchAll();
    }
}