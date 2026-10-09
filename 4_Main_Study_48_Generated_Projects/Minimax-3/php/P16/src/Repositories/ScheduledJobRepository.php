<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class ScheduledJobRepository
{
    public function all(?int $ownerId = null): array
    {
        $sql = 'SELECT j.*, p.code AS profile_code, p.name AS profile_name, u.username AS owner_username
                  FROM scheduled_jobs j
                  JOIN job_profiles p ON p.id = j.profile_id
                  JOIN users u ON u.id = j.owner_id';
        $params = [];
        if ($ownerId !== null) {
            $sql .= ' WHERE j.owner_id = :oid';
            $params[':oid'] = $ownerId;
        }
        $sql .= ' ORDER BY j.created_at DESC';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT j.*, p.code AS profile_code, p.name AS profile_name, u.username AS owner_username
               FROM scheduled_jobs j
               JOIN job_profiles p ON p.id = j.profile_id
               JOIN users u ON u.id = j.owner_id
              WHERE j.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $ownerId, int $profileId, string $name, string $cron): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO scheduled_jobs (owner_id, profile_id, name, cron_expr, state, next_run_at)
             VALUES (:o, :p, :n, :cron, :state, :next)'
        );
        $nextRun = (new \DateTimeImmutable('+5 minutes'))->format('Y-m-d H:i:s');
        $stmt->execute([
            ':o' => $ownerId,
            ':p' => $profileId,
            ':n' => $name,
            ':cron' => $cron,
            ':state' => 'pending',
            ':next' => $nextRun,
        ]);
        return (int)Connection::get()->lastInsertId();
    }

    public function transition(int $id, string $newState): bool
    {
        $current = $this->findById($id);
        if (!$current) {
            return false;
        }
        $allowed = [
            'pending'   => ['queued', 'running', 'deleted'],
            'queued'    => ['running', 'paused', 'deleted'],
            'running'   => ['completed', 'failed', 'paused'],
            'paused'    => ['queued', 'deleted'],
            'completed' => ['pending', 'deleted'],
            'failed'    => ['pending', 'deleted'],
            'deleted'   => [],
        ];
        if (!in_array($newState, $allowed[$current['state']] ?? [], true) && $current['state'] !== $newState) {
            return false;
        }
        $stmt = Connection::get()->prepare(
            'UPDATE scheduled_jobs SET state = :s, updated_at = datetime(\'now\') WHERE id = :id'
        );
        $stmt->execute([':s' => $newState, ':id' => $id]);
        return true;
    }

    public function markRun(int $id, string $ranAt): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE scheduled_jobs SET last_run_at = :ra, updated_at = datetime(\'now\') WHERE id = :id'
        );
        $stmt->execute([':ra' => $ranAt, ':id' => $id]);
    }
}