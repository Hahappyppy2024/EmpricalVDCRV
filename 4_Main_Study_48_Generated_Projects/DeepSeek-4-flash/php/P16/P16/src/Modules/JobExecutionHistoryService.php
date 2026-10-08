<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-06 Job execution history.
 *
 * Persistent entities: User, Session, JobExecutionHistory (job run log).
 * Users inspect job outputs, exit status, duration and retry history.
 */
final class JobExecutionHistoryService extends BaseService
{
    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT jr.*, j.name AS job_name, j.profile, u.username AS owner_name
                FROM job_runs jr
                JOIN jobs j ON j.id = jr.job_id
                JOIN users u ON u.id = j.owner_id WHERE 1 = 1';
        $params = [];
        if (!empty($filters['job_id'])) {
            $sql .= ' AND jr.job_id = :job';
            $params['job'] = (int) $filters['job_id'];
        }
        if (isset($filters['exit_status']) && $filters['exit_status'] !== '') {
            $sql .= ' AND jr.exit_status = :exit';
            $params['exit'] = (int) $filters['exit_status'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND jr.output LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        // Operators see runs of their own jobs only; admins see everything.
        if (($user['role'] ?? '') !== 'admin') {
            $sql .= ' AND j.owner_id = :uid';
            $params['uid'] = (int) $user['id'];
        }
        $limit = min((int) ($filters['limit'] ?? 200), 1000);
        $sql .= ' ORDER BY jr.id DESC LIMIT ' . $limit;

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        $row = $this->db->fetchOne(
            'SELECT jr.*, j.name AS job_name, j.profile, j.owner_id, u.username AS owner_name
             FROM job_runs jr
             JOIN jobs j ON j.id = jr.job_id
             JOIN users u ON u.id = j.owner_id WHERE jr.id = ?',
            [$id]
        );
        if ($row === null) {
            throw new \App\NotFoundException('Run record not found.');
        }
        $this->assertScoped($row, $user);

        return $row;
    }

    public function create(array $data, array $user): array
    {
        $jobId = (int) ($data['job_id'] ?? 0);
        $job = $this->db->fetchOne('SELECT * FROM jobs WHERE id = ? AND status != \'deleted\'', [$jobId]);
        if ($job === null) {
            throw new \App\NotFoundException('Job not found.');
        }
        $this->assertOwnedJob($job, $user);

        (new Validator())
            ->number($data, 'exit_status', -1, null, true)
            ->number($data, 'duration_ms', 0, null, false)
            ->number($data, 'retry_count', 0, null, false)
            ->string(['output' => $data['output'] ?? ''], 'output', 4000)
            ->throwIfInvalid();

        $lastRun = $this->db->fetchValue('SELECT MAX(run_number) FROM job_runs WHERE job_id = ?', [$jobId]) ?? 0;
        $runNumber = ((int) $lastRun) + 1;
        $now = $this->db->now();
        $id = $this->db->insert('job_runs', [
            'job_id' => $jobId,
            'run_number' => $runNumber,
            'output' => trim((string) ($data['output'] ?? '')),
            'exit_status' => (int) $data['exit_status'],
            'duration_ms' => (int) ($data['duration_ms'] ?? 0),
            'retry_count' => (int) ($data['retry_count'] ?? 0),
            'started_at' => (string) ($data['started_at'] ?? $now),
            'finished_at' => $now,
        ]);
        $this->log($user, 'job_run', 'job_execution_history', 'Manual run #' . $runNumber . ' recorded for job "' . $job['name'] . '".');

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('job_runs', $id);
        $job = $this->db->fetchOne('SELECT * FROM jobs WHERE id = ?', [(int) $row['job_id']]);
        if ($job === null) {
            throw new \App\NotFoundException('Job not found.');
        }
        $this->assertOwnedJob($job, $user);

        $validator = new Validator();
        $updates = [];
        if (array_key_exists('output', $data)) {
            $validator->string(['output' => $data['output']], 'output', 4000);
            $updates['output'] = trim((string) $data['output']);
        }
        if (array_key_exists('exit_status', $data)) {
            $validator->number($data, 'exit_status', -1, null, true);
            $updates['exit_status'] = (int) $data['exit_status'];
        }
        if (array_key_exists('duration_ms', $data)) {
            $validator->number($data, 'duration_ms', 0, null, true);
            $updates['duration_ms'] = (int) $data['duration_ms'];
        }
        if (array_key_exists('retry_count', $data)) {
            $validator->number($data, 'retry_count', 0, null, true);
            $updates['retry_count'] = (int) $data['retry_count'];
        }
        $validator->throwIfInvalid();
        if ($updates !== []) {
            $this->db->update('job_runs', $updates, ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    public function delete(int $id, array $user): void
    {
        $row = $this->requireRow('job_runs', $id);
        $job = $this->db->fetchOne('SELECT * FROM jobs WHERE id = ?', [(int) $row['job_id']]);
        if ($job === null) {
            throw new \App\NotFoundException('Job not found.');
        }
        $this->assertOwnedJob($job, $user);
        $this->db->delete('job_runs', ['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function assertScoped(array $row, array $user): void
    {
        if (($user['role'] ?? '') === 'admin') {
            return;
        }
        if ((int) ($row['owner_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            throw new \App\NotFoundException('Run record not found.');
        }
    }

    /** @param array<string, mixed> $job */
    private function assertOwnedJob(array $job, array $user): void
    {
        if (($user['role'] ?? '') === 'admin') {
            return;
        }
        if ((int) ($job['owner_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            throw new \App\NotFoundException('Job not found.');
        }
    }
}
