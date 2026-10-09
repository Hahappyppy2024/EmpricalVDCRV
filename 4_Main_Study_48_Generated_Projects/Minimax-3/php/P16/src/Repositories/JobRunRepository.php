<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class JobRunRepository
{
    public function forJob(int $jobId, int $limit = 100): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM job_runs WHERE job_id = :j ORDER BY started_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':j', $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function recent(int $limit = 100): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT r.*, j.name AS job_name, j.profile_id, p.code AS profile_code
               FROM job_runs r
               JOIN scheduled_jobs j ON j.id = r.job_id
               JOIN job_profiles p ON p.id = j.profile_id
              ORDER BY r.started_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT r.*, j.name AS job_name, j.owner_id, p.code AS profile_code, u.username AS owner_username
               FROM job_runs r
               JOIN scheduled_jobs j ON j.id = r.job_id
               JOIN job_profiles p ON p.id = j.profile_id
               JOIN users u ON u.id = j.owner_id
              WHERE r.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function start(int $jobId): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO job_runs (job_id, status, output, error_output) VALUES (:j, :s, :out, :err)'
        );
        $stmt->execute([':j' => $jobId, ':s' => 'running', ':out' => '', ':err' => '']);
        return (int)Connection::get()->lastInsertId();
    }

    public function finish(int $id, string $status, int $exitCode, string $output, string $errorOutput, int $durationMs): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE job_runs
                SET status = :s,
                    exit_code = :ec,
                    output = :o,
                    error_output = :e,
                    duration_ms = :d,
                    finished_at = datetime(\'now\')
              WHERE id = :id'
        );
        $stmt->execute([
            ':s' => $status,
            ':ec' => $exitCode,
            ':o' => $output,
            ':e' => $errorOutput,
            ':d' => $durationMs,
            ':id' => $id,
        ]);
    }
}