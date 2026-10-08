<?php

declare(strict_types=1);

namespace CloudFS\Services;

use CloudFS\Database\Database;

final class QuotaService
{
    public function __construct(private Database $db)
    {
    }

    public function recompute(int $userId): int
    {
        $used = (int) $this->db->value(
            'SELECT COALESCE(SUM(size_bytes), 0) FROM files WHERE owner_id = ? AND status = \'active\'',
            [$userId]
        );
        $this->db->run(
            'INSERT INTO storage_quota (user_id, used_bytes, updated_at) VALUES (?, ?, datetime(\'now\'))
             ON CONFLICT(user_id) DO UPDATE SET used_bytes = excluded.used_bytes, updated_at = datetime(\'now\')',
            [$userId, $used]
        );
        return $used;
    }

    public function usedBytes(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COALESCE(used_bytes, 0) FROM storage_quota WHERE user_id = ?',
            [$userId]
        );
    }

    public function remaining(int $userId, int $quotaBytes): int
    {
        $used = $this->usedBytes($userId);
        return max(0, $quotaBytes - $used);
    }

    public function canAccommodate(int $userId, int $newBytes): bool
    {
        $user = $this->db->one('SELECT quota_bytes FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return false;
        }
        return ($this->usedBytes($userId) + $newBytes) <= (int) $user['quota_bytes'];
    }

    public function summary(int $userId): array
    {
        $user = $this->db->one('SELECT quota_bytes FROM users WHERE id = ?', [$userId]);
        $quotaBytes = $user ? (int) $user['quota_bytes'] : 0;
        $used = $this->usedBytes($userId);
        $quotaRow = $this->db->one('SELECT * FROM storage_quota WHERE user_id = ?', [$userId]);
        return [
            'user_id' => $userId,
            'quota_bytes' => $quotaBytes,
            'used_bytes' => $used,
            'remaining_bytes' => max(0, $quotaBytes - $used),
            'soft_limit' => (int) ($quotaRow['soft_limit'] ?? $quotaBytes),
            'hard_limit' => (int) ($quotaRow['hard_limit'] ?? $quotaBytes),
            'percent' => $quotaBytes > 0 ? round(($used / $quotaBytes) * 100, 2) : 0,
        ];
    }

    public function listAll(): array
    {
        return $this->db->all(
            'SELECT u.id, u.username, u.quota_bytes, COALESCE(q.used_bytes, 0) AS used_bytes,
                    COALESCE(q.soft_limit, u.quota_bytes) AS soft_limit,
                    COALESCE(q.hard_limit, u.quota_bytes) AS hard_limit
             FROM users u LEFT JOIN storage_quota q ON q.user_id = u.id
             ORDER BY u.username'
        );
    }

    public function updateQuota(int $userId, int $quotaBytes, int $softLimit, int $hardLimit): void
    {
        $this->db->run('UPDATE users SET quota_bytes = ?, updated_at = datetime(\'now\') WHERE id = ?', [$quotaBytes, $userId]);
        $this->db->run(
            'INSERT INTO storage_quota (user_id, used_bytes, soft_limit, hard_limit, updated_at) VALUES (?, (SELECT COALESCE(SUM(size_bytes),0) FROM files WHERE owner_id = ? AND status = \'active\'), ?, ?, datetime(\'now\'))
             ON CONFLICT(user_id) DO UPDATE SET soft_limit = excluded.soft_limit, hard_limit = excluded.hard_limit, updated_at = datetime(\'now\')',
            [$userId, $userId, $softLimit, $hardLimit]
        );
    }
}
