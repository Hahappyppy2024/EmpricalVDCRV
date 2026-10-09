<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class MetricSnapshotRepository
{
    public function latest(string $host = 'localhost'): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM metric_snapshots WHERE host = :h ORDER BY captured_at DESC LIMIT 1'
        );
        $stmt->execute([':h' => $host]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function history(string $host, int $limit = 50): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM metric_snapshots WHERE host = :h ORDER BY captured_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':h', $host, \PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function record(array $data): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO metric_snapshots (host, cpu_pct, memory_pct, disk_pct, uptime_sec, load_avg, service_state)
             VALUES (:h, :cpu, :mem, :disk, :up, :load, :state)'
        );
        $stmt->execute([
            ':h'     => $data['host'],
            ':cpu'   => $data['cpu_pct'],
            ':mem'   => $data['memory_pct'],
            ':disk'  => $data['disk_pct'],
            ':up'    => $data['uptime_sec'],
            ':load'  => $data['load_avg'] ?? 0.0,
            ':state' => $data['service_state'] ?? 'healthy',
        ]);
        return (int)Connection::get()->lastInsertId();
    }

    public function distinctHosts(): array
    {
        return Connection::get()->query('SELECT DISTINCT host FROM metric_snapshots ORDER BY host')->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function generateDeterministicSeries(string $host): array
    {
        $existing = $this->history($host, 1);
        if (!empty($existing)) {
            return [];
        }
        $rows = [];
        $seed = 1000;
        for ($i = 0; $i < 24; $i++) {
            $cpu = 20 + (($seed * ($i + 1) * 7) % 60);
            $mem = 30 + (($seed * ($i + 1) * 11) % 50);
            $disk = 45 + ($i % 10);
            $uptime = 3600 * (24 - $i);
            $load = round(($cpu / 50.0) + 0.1, 2);
            $state = $cpu > 80 ? 'warning' : 'healthy';
            $rows[] = [
                'host' => $host,
                'cpu_pct' => $cpu,
                'memory_pct' => $mem,
                'disk_pct' => $disk,
                'uptime_sec' => $uptime,
                'load_avg' => $load,
                'service_state' => $state,
                'captured_at' => (new \DateTimeImmutable('-' . (24 - $i) . ' hours'))->format('Y-m-d H:i:s'),
            ];
        }
        $stmt = Connection::get()->prepare(
            'INSERT INTO metric_snapshots (host, cpu_pct, memory_pct, disk_pct, uptime_sec, load_avg, service_state, captured_at)
             VALUES (:h, :cpu, :mem, :disk, :up, :load, :state, :cap)'
        );
        foreach ($rows as $r) {
            $stmt->execute([
                ':h' => $r['host'],
                ':cpu' => $r['cpu_pct'],
                ':mem' => $r['memory_pct'],
                ':disk' => $r['disk_pct'],
                ':up' => $r['uptime_sec'],
                ':load' => $r['load_avg'],
                ':state' => $r['service_state'],
                ':cap' => $r['captured_at'],
            ]);
        }
        return $rows;
    }
}