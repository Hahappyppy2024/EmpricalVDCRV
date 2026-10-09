<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class SettingsRepository
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        $rows = $this->pdo->query('SELECT key, value FROM settings ORDER BY key')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (string)$v : $default;
    }

    public function set(string $key, string $value): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (key, value, updated_at) VALUES (?, ?, datetime(\'now\'))
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime(\'now\')'
        );
        return $stmt->execute([$key, $value]);
    }
}
