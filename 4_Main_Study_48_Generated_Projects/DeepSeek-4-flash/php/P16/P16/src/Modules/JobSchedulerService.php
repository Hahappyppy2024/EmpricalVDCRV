<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-05 Job scheduler.
 *
 * Persistent entities: User, Session, JobScheduler (jobs).
 * Users create/pause/run/delete jobs built from approved profiles; running a
 * job deterministically produces a JobExecutionHistory run record.
 */
final class JobSchedulerService extends BaseService
{
    public const PROFILES = ['nightly_backup', 'log_rotate', 'health_check', 'metric_aggregation', 'report_generation'];
    public const STATUSES = ['active', 'paused', 'deleted'];
    public const SCHEDULES = ['manual', 'hourly', 'daily', 'weekly', 'monthly'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT j.*, u.username AS owner_name
                FROM jobs j JOIN users u ON u.id = j.owner_id WHERE j.status != \'deleted\'';
        $params = [];
        if (($user['role'] ?? '') !== 'admin') {
            $sql .= ' AND j.owner_id = :uid';
            $params['uid'] = (int) $user['id'];
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= ' AND j.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['profile'])) {
            $sql .= ' AND j.profile = :profile';
            $params['profile'] = $filters['profile'];
        }
        $sql .= ' ORDER BY j.id DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        $row = $this->db->fetchOne(
            'SELECT j.*, u.username AS owner_name FROM jobs j JOIN users u ON u.id = j.owner_id WHERE j.id = ?',
            [$id]
        );
        if ($row === null) {
            throw new \App\NotFoundException('Job not found.');
        }
        $this->assertOwned($row, $user);

        return $row;
    }

    public function create(array $data, array $user): array
    {
        $profile = (string) ($data['profile'] ?? '');
        (new Validator())
            ->required($data, 'name', 'profile')
            ->string(['name' => $data['name'] ?? ''], 'name', 120, true)
            ->string(['description' => $data['description'] ?? ''], 'description', 300)
            ->oneOf(['profile' => $profile], 'profile', self::PROFILES, true)
            ->oneOf(['schedule' => $data['schedule'] ?? 'manual'], 'schedule', self::SCHEDULES, true)
            ->throwIfInvalid();

        $id = $this->db->insert('jobs', [
            'name' => trim((string) $data['name']),
            'profile' => $profile,
            'description' => trim((string) ($data['description'] ?? '')),
            'schedule' => (string) ($data['schedule'] ?? 'manual'),
            'status' => 'active',
            'owner_id' => (int) $user['id'],
            'created_at' => $this->db->now(),
            'updated_at' => $this->db->now(),
        ]);
        $this->log($user, 'job_create', 'job_scheduler', 'Job "' . $data['name'] . '" created from profile ' . $profile . '.');

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('jobs', $id);
        $this->assertOwned($row, $user);

        if (array_key_exists('action', $data)) {
            return $this->performAction($row, (string) $data['action'], $user);
        }

        $updates = [];
        if (array_key_exists('name', $data)) {
            (new Validator())->string(['name' => $data['name']], 'name', 120, true)->throwIfInvalid();
            $updates['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('schedule', $data)) {
            (new Validator())->oneOf($data, 'schedule', self::SCHEDULES, true)->throwIfInvalid();
            $updates['schedule'] = $data['schedule'];
        }
        if (array_key_exists('description', $data)) {
            (new Validator())->string(['description' => $data['description']], 'description', 300)->throwIfInvalid();
            $updates['description'] = trim((string) $data['description']);
        }
        if ($updates !== []) {
            $updates['updated_at'] = $this->db->now();
            $this->db->update('jobs', $updates, ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    /** @param array<string, mixed> $row */
    private function performAction(array $row, string $action, array $user): array
    {
        $current = (string) $row['status'];
        switch ($action) {
            case 'pause':
                if ($current !== 'active') {
                    throw new \App\ValidationException('Only active jobs can be paused.');
                }
                $this->db->update('jobs', ['status' => 'paused', 'updated_at' => $this->db->now()], ['id' => (int) $row['id']]);
                $this->log($user, 'job_pause', 'job_scheduler', 'Job "' . $row['name'] . '" paused.');
                break;
            case 'resume':
                if ($current !== 'paused') {
                    throw new \App\ValidationException('Only paused jobs can be resumed.');
                }
                $this->db->update('jobs', ['status' => 'active', 'updated_at' => $this->db->now()], ['id' => (int) $row['id']]);
                $this->log($user, 'job_resume', 'job_scheduler', 'Job "' . $row['name'] . '" resumed.');
                break;
            case 'run':
                if ($current === 'deleted') {
                    throw new \App\ValidationException('A deleted job cannot run.');
                }
                $this->runJob($row, $user);
                break;
            default:
                throw new \App\ValidationException('Invalid job action.');
        }

        return $this->item((int) $row['id'], $user);
    }

    /** Deterministic background-run simulation for a job. */
    private function runJob(array $row, array $user): void
    {
        $profile = (string) $row['profile'];
        $seed = crc32($profile . ':' . (int) $row['id']);
        $exit = ($seed % 5 === 0) ? 1 : 0;
        $retries = ($seed % 3 === 0) ? 1 : 0;
        $duration = 150 + ($seed % 4200);
        $lastRun = $this->db->fetchValue('SELECT MAX(run_number) FROM job_runs WHERE job_id = ?', [(int) $row['id']]) ?? 0;
        $runNumber = ((int) $lastRun) + 1;
        $output = sprintf(
            "[%s] profile=%s job=%s run=%d exit=%d retries=%d duration=%dms%s",
            date('Y-m-d H:i:s'),
            $profile,
            $row['name'],
            $runNumber,
            $exit,
            $retries,
            $duration,
            $exit === 0 ? ' OK' : ' FAILED'
        );

        $this->db->insert('job_runs', [
            'job_id' => (int) $row['id'],
            'run_number' => $runNumber,
            'output' => $output,
            'exit_status' => $exit,
            'duration_ms' => $duration,
            'retry_count' => $retries,
            'started_at' => $this->db->now(),
            'finished_at' => $this->db->now(),
        ]);
        $this->db->update('jobs', ['updated_at' => $this->db->now()], ['id' => (int) $row['id']]);
        $this->log($user, 'job_run', 'job_scheduler', 'Job "' . $row['name'] . '" executed (run #' . $runNumber . ', exit ' . $exit . ').');
    }

    public function delete(int $id, array $user): void
    {
        $row = $this->requireRow('jobs', $id);
        $this->assertOwned($row, $user);
        // Soft delete keeps execution history intact.
        $this->db->update('jobs', ['status' => 'deleted', 'updated_at' => $this->db->now()], ['id' => $id]);
        $this->log($user, 'job_delete', 'job_scheduler', 'Job "' . $row['name'] . '" deleted.');
    }

    /** @param array<string, mixed> $row */
    private function assertOwned(array $row, array $user): void
    {
        if (($user['role'] ?? '') === 'admin') {
            return;
        }
        if ((int) ($row['owner_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            throw new \App\NotFoundException('Job not found.');
        }
    }
}
