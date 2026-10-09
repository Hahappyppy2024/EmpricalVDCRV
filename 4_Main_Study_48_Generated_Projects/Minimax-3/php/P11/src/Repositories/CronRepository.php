<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class CronRepository
{
    public function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM cron_jobs WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM cron_jobs WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function exists(int $userId, string $name): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM cron_jobs WHERE user_id = ? AND name = ?');
        $stmt->execute([$userId, $name]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(int $userId, string $name, string $schedule, string $command, bool $active): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO cron_jobs (user_id, name, schedule, command, is_active) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $name, $schedule, $command, $active ? 1 : 0]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function update(int $id, int $userId, string $name, string $schedule, string $command, bool $active): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE cron_jobs SET name = ?, schedule = ?, command = ?, is_active = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$name, $schedule, $command, $active ? 1 : 0, $id, $userId]);
    }

    public function markRun(int $id, int $userId, string $status): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE cron_jobs SET last_run = datetime("now"), last_status = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$status, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM cron_jobs WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}