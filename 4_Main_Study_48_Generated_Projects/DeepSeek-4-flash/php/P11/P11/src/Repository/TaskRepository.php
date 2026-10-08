<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class TaskRepository extends BaseRepository
{
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM scheduled_tasks WHERE user_id = ? ORDER BY id');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM scheduled_tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $task = $stmt->fetch();

        return $task ?: null;
    }

    public function create(int $userId, string $name, string $command, string $schedule, int $enabled): int
    {
        $now = date('Y-m-d H:i:s');
        $nextRun = $enabled ? $this->nextRunFromSchedule($schedule, $now) : null;
        $stmt = $this->db->prepare(
            'INSERT INTO scheduled_tasks (user_id, name, command, schedule, enabled, status, last_run, next_run, last_output, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $name, $command, $schedule, $enabled, 'idle', null, $nextRun, '', $now]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $command, string $schedule, int $enabled): void
    {
        $task = $this->findById($id);
        $now = date('Y-m-d H:i:s');
        $nextRun = $enabled ? $this->nextRunFromSchedule($schedule, $now) : ($task['next_run'] ?? null);
        $stmt = $this->db->prepare(
            'UPDATE scheduled_tasks SET name = ?, command = ?, schedule = ?, enabled = ?, next_run = ? WHERE id = ?'
        );
        $stmt->execute([$name, $command, $schedule, $enabled, $nextRun, $id]);
    }

    public function toggle(int $id): void
    {
        $task = $this->findById($id);
        $enabled = (int) $task['enabled'] === 1 ? 0 : 1;
        $now = date('Y-m-d H:i:s');
        $nextRun = $enabled ? $this->nextRunFromSchedule($task['schedule'], $now) : null;
        $this->db->prepare('UPDATE scheduled_tasks SET enabled = ?, next_run = ? WHERE id = ?')->execute([$enabled, $nextRun, $id]);
    }

    public function markRun(int $id, string $status, string $output): void
    {
        $now = date('Y-m-d H:i:s');
        $task = $this->findById($id);
        $nextRun = $this->nextRunFromSchedule($task['schedule'], $now);
        $this->db->prepare('UPDATE scheduled_tasks SET status = ?, last_run = ?, next_run = ?, last_output = ? WHERE id = ?')
            ->execute([$status, $now, $nextRun, $output, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM scheduled_tasks WHERE id = ?')->execute([$id]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM scheduled_tasks WHERE id = ?');
        $stmt->execute([$id]);
        $task = $stmt->fetch();

        return $task ?: null;
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM scheduled_tasks WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Deterministic scheduler: parse a standard 5-field cron expression and
     * return the next minute boundary within the next 60 days.
     */
    public function nextRunFromSchedule(string $schedule, string $from): ?string
    {
        $parts = preg_split('/\s+/', trim($schedule));
        if (count($parts) !== 5) {
            return null;
        }
        $fromTs = strtotime($from);
        for ($i = 1; $i <= 60 * 24 * 60; $i++) {
            $ts = $fromTs + $i * 60;
            $minute = (int) date('i', $ts);
            $hour = (int) date('G', $ts);
            $day = (int) date('j', $ts);
            $month = (int) date('n', $ts);
            $dow = (int) date('w', $ts);
            if ($this->cronFieldMatches($parts[0], $minute)
                && $this->cronFieldMatches($parts[1], $hour)
                && $this->cronFieldMatches($parts[2], $day)
                && $this->cronFieldMatches($parts[3], $month)
                && $this->cronFieldMatches($parts[4], $dow)
            ) {
                return date('Y-m-d H:i:00', $ts);
            }
        }

        return null;
    }

    private function cronFieldMatches(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }
        foreach (explode(',', $field) as $item) {
            if (strpos($item, '/') !== false) {
                [$base, $step] = explode('/', $item, 2);
                $start = $base === '*' ? 0 : (int) $base;
                $step = (int) $step;
                if ($step > 0 && $value >= $start && ($value - $start) % $step === 0) {
                    return true;
                }
                continue;
            }
            if (strpos($item, '-') !== false) {
                [$lo, $hi] = explode('-', $item, 2);
                if ($value >= (int) $lo && $value <= (int) $hi) {
                    return true;
                }
                continue;
            }
            if ((int) $item === $value) {
                return true;
            }
        }

        return false;
    }
}
