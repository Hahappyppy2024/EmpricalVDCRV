<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UsageRepository extends BaseRepository
{
    public function historyForUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->db->prepare('SELECT * FROM resource_usage WHERE user_id = ? ORDER BY recorded_at ASC LIMIT ?');
        $stmt->execute([$userId, $limit]);

        return $stmt->fetchAll();
    }

    public function latestForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM resource_usage WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function record(int $userId, float $cpu, int $disk, int $traffic, float $quotaPercent): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO resource_usage (user_id, recorded_at, cpu_usage, disk_used, traffic_used, quota_percent) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, date('Y-m-d H:i:s'), $cpu, $disk, $traffic, $quotaPercent]);

        return (int) $this->db->lastInsertId();
    }
}
