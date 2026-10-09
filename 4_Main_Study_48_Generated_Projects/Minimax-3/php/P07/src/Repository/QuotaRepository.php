<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class QuotaRepository
{
    public function getForUser(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM storage_quotas WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsert(int $userId, int $limit, int $used = 0): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO storage_quotas (user_id, limit_bytes, used_bytes) VALUES (?, ?, ?) ON CONFLICT(user_id) DO UPDATE SET limit_bytes = excluded.limit_bytes, used_bytes = excluded.used_bytes, updated_at = datetime(\'now\')');
        $stmt->execute([$userId, $limit, $used]);
    }

    public function adjustUsed(int $userId, int $delta): void
    {
        Database::pdo()->prepare('UPDATE storage_quotas SET used_bytes = MAX(used_bytes + ?, 0), updated_at = datetime(\'now\') WHERE user_id = ?')->execute([$delta, $userId]);
    }

    public function recalc(int $userId, int $used): void
    {
        Database::pdo()->prepare('UPDATE storage_quotas SET used_bytes = ?, updated_at = datetime(\'now\') WHERE user_id = ?')->execute([$used, $userId]);
    }

    public function log(int $quotaId, ?int $userId, string $action, bool $success, array $details = []): void
    {
        Database::pdo()->prepare('INSERT INTO storage_quota_log (quota_id, user_id, action, success, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$quotaId, $userId, $action, $success ? 1 : 0, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }
}