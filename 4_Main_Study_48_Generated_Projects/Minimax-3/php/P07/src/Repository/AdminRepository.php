<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class AdminRepository
{
    public function settings(): ?array
    {
        $row = Database::pdo()->query('SELECT * FROM admin_settings WHERE id = 1')->fetch();
        return $row ?: null;
    }

    public function init(array $defaults, int $updatedBy): void
    {
        Database::pdo()->prepare('INSERT OR REPLACE INTO admin_settings (id, default_user_quota, default_team_quota, retention_days, blocked_file_types, updated_by) VALUES (1, ?, ?, ?, ?, ?)')
            ->execute([
                $defaults['default_user_quota'],
                $defaults['default_team_quota'],
                $defaults['retention_days'],
                $defaults['blocked_file_types'],
                $updatedBy,
            ]);
    }

    public function update(array $fields, int $updatedBy): void
    {
        $allowed = ['default_user_quota', 'default_team_quota', 'retention_days', 'blocked_file_types'];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = "$key = ?";
                $params[] = $fields[$key];
            }
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_by = ?';
        $params[] = $updatedBy;
        $sets[] = "updated_at = datetime('now')";
        Database::pdo()->prepare('UPDATE admin_settings SET ' . implode(', ', $sets) . ' WHERE id = 1')->execute($params);
    }

    public function log(?int $settingId, ?int $userId, string $action, bool $success, array $details = []): void
    {
        Database::pdo()->prepare('INSERT INTO admin_console_log (setting_id, user_id, action, success, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$settingId, $userId, $action, $success ? 1 : 0, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }
}