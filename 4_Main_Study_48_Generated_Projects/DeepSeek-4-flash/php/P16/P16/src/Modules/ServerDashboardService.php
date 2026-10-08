<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-02 Server dashboard.
 *
 * Persistent entities: User, Session, ServerDashboard (metric snapshots).
 * Metric snapshots carry cpu/memory/disk usage, uptime and service status.
 */
final class ServerDashboardService extends BaseService
{
    public const SERVICE_STATUS = ['ok', 'degraded', 'down', 'maintenance', 'unknown'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT sd.*, u.username AS recorded_by_name
                FROM server_dashboard sd
                LEFT JOIN users u ON u.id = sd.recorded_by WHERE 1 = 1';
        $params = [];
        if (!empty($filters['server_name'])) {
            $sql .= ' AND sd.server_name = :server';
            $params['server'] = $filters['server_name'];
        }
        if (!empty($filters['service_status'])) {
            $sql .= ' AND sd.service_status = :status';
            $params['status'] = $filters['service_status'];
        }
        $sql .= ' ORDER BY sd.id DESC LIMIT 500';

        return $this->db->fetchAll($sql, $params);
    }

    public function latest(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT sd.*, u.username AS recorded_by_name
             FROM server_dashboard sd
             LEFT JOIN users u ON u.id = sd.recorded_by
             WHERE sd.id IN (SELECT MAX(id) FROM server_dashboard GROUP BY server_name)
             ORDER BY sd.server_name ASC'
        );

        return $rows;
    }

    public function item(int $id, array $user): array
    {
        $row = $this->db->fetchOne(
            'SELECT sd.*, u.username AS recorded_by_name
             FROM server_dashboard sd LEFT JOIN users u ON u.id = sd.recorded_by WHERE sd.id = ?',
            [$id]
        );
        if ($row === null) {
            throw new \App\NotFoundException('Record not found.');
        }

        return $row;
    }

    public function create(array $data, array $user): array
    {
        (new Validator())
            ->required($data, 'server_name', 'service_status')
            ->string(['server_name' => $data['server_name'] ?? ''], 'server_name', 100, true)
            ->string(['hostname' => $data['hostname'] ?? ''], 'hostname', 100)
            ->number($data, 'cpu_pct', 0, 100, true)
            ->number($data, 'memory_pct', 0, 100, true)
            ->number($data, 'disk_pct', 0, 100, true)
            ->number($data, 'uptime_seconds', 0, null, false)
            ->oneOf($data, 'service_status', self::SERVICE_STATUS, true)
            ->throwIfInvalid();

        $id = $this->db->insert('server_dashboard', [
            'server_name' => trim((string) $data['server_name']),
            'hostname' => trim((string) ($data['hostname'] ?? '')),
            'cpu_pct' => (float) $data['cpu_pct'],
            'memory_pct' => (float) $data['memory_pct'],
            'disk_pct' => (float) $data['disk_pct'],
            'uptime_seconds' => (int) ($data['uptime_seconds'] ?? 0),
            'service_status' => $data['service_status'],
            'recorded_by' => (int) $user['id'],
            'created_at' => $this->db->now(),
        ]);

        $this->log($user, 'access_record', 'server_dashboard', 'Metric snapshot #' . $id . ' recorded for ' . $data['server_name'] . '.');

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('server_dashboard', $id);
        $this->assertOwned($row, $user);

        $validator = (new Validator());
        $updates = [];
        foreach (['cpu_pct' => null, 'memory_pct' => null, 'disk_pct' => null, 'uptime_seconds' => null] as $f => $_) {
            if (array_key_exists($f, $data)) {
                $validator->number($data, $f, 0, $f === 'uptime_seconds' ? null : 100);
                $updates[$f] = $f === 'uptime_seconds' ? (int) $data[$f] : (float) $data[$f];
            }
        }
        if (array_key_exists('service_status', $data)) {
            $validator->oneOf($data, 'service_status', self::SERVICE_STATUS);
            if ($data['service_status'] !== '') {
                $updates['service_status'] = $data['service_status'];
            }
        }
        if (array_key_exists('hostname', $data)) {
            $validator->string($data, 'hostname', 100);
            $updates['hostname'] = trim((string) $data['hostname']);
        }
        $validator->throwIfInvalid();
        if ($updates !== []) {
            $this->db->update('server_dashboard', $updates, ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    public function delete(int $id, array $user): void
    {
        $row = $this->requireRow('server_dashboard', $id);
        $this->assertOwned($row, $user);
        $this->db->delete('server_dashboard', ['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function assertOwned(array $row, array $user): void
    {
        if (($user['role'] ?? '') === 'admin') {
            return;
        }
        if ((int) ($row['recorded_by'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            throw new \App\NotFoundException('Record not found.');
        }
    }
}
