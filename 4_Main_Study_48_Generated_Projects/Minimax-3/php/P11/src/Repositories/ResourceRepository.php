<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class ResourceRepository
{
    public function forUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM resource_usage WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 30');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function summary(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT AVG(cpu_percent) AS avg_cpu, MAX(cpu_percent) AS max_cpu,
                    AVG(disk_used_mb) AS avg_disk, MAX(disk_used_mb) AS max_disk,
                    SUM(bandwidth_used_mb) AS total_bw, SUM(emails_sent) AS total_emails
             FROM resource_usage WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch() ?: [];
        $planDisk = 0;
        $planBw = 0;
        $userStmt = Database::pdo()->prepare('SELECT u.plan_id, p.disk_quota_mb, p.bandwidth_quota_mb FROM users u LEFT JOIN plans p ON p.id = u.plan_id WHERE u.id = ?');
        $userStmt->execute([$userId]);
        $u = $userStmt->fetch();
        if ($u) {
            $planDisk = (int)$u['disk_quota_mb'];
            $planBw = (int)$u['bandwidth_quota_mb'];
        }
        return [
            'avg_cpu' => round((float)($row['avg_cpu'] ?? 0), 2),
            'max_cpu' => round((float)($row['max_cpu'] ?? 0), 2),
            'avg_disk' => round((float)($row['avg_disk'] ?? 0), 2),
            'max_disk' => (int)($row['max_disk'] ?? 0),
            'total_bw' => (int)($row['total_bw'] ?? 0),
            'total_emails' => (int)($row['total_emails'] ?? 0),
            'plan_disk_mb' => $planDisk,
            'plan_bw_mb'   => $planBw,
        ];
    }

    public function record(int $userId, string $period, float $cpu, int $disk, int $bw, int $emails): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO resource_usage (user_id, period, cpu_percent, disk_used_mb, bandwidth_used_mb, emails_sent) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $period, $cpu, $disk, $bw, $emails]);
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM resource_usage WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function updateReport(int $id, int $userId, float $cpu, int $disk, int $bw, int $emails): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE resource_usage SET cpu_percent = ?, disk_used_mb = ?, bandwidth_used_mb = ?, emails_sent = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$cpu, $disk, $bw, $emails, $id, $userId]);
    }
}