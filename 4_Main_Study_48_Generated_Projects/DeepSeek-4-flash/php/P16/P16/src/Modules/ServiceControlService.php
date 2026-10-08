<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-04 Service control.
 *
 * Persistent entities: User, Session, ServiceControl (mock services).
 * Operators and admins start/stop/restart mock services; every privileged
 * action is written to the audit log.
 */
final class ServiceControlService extends BaseService
{
    public const STATUSES = ['stopped', 'starting', 'running', 'stopping', 'restarting'];
    public const ACTIONS = ['start', 'stop', 'restart', 'inspect'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT ms.*, u.username AS controlled_by_name
                FROM mock_services ms
                LEFT JOIN users u ON u.id = ms.controlled_by WHERE 1 = 1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND ms.status = :status';
            $params['status'] = $filters['status'];
        }
        $sql .= ' ORDER BY ms.name ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        return $this->db->fetchOne(
            'SELECT ms.*, u.username AS controlled_by_name
             FROM mock_services ms LEFT JOIN users u ON u.id = ms.controlled_by WHERE ms.id = ?',
            [$id]
        ) ?? throw new \App\NotFoundException('Service not found.');
    }

    public function create(array $data, array $user): array
    {
        (new Validator())
            ->required($data, 'name')
            ->string(['name' => $data['name'] ?? ''], 'name', 100, true)
            ->string(['description' => $data['description'] ?? ''], 'description', 300)
            ->unique($this->db, 'mock_services', 'name', trim((string) ($data['name'] ?? '')))
            ->throwIfInvalid();

        $id = $this->db->insert('mock_services', [
            'name' => trim((string) $data['name']),
            'description' => trim((string) ($data['description'] ?? '')),
            'status' => 'stopped',
            'uptime_seconds' => 0,
            'controlled_by' => (int) $user['id'],
            'last_action' => 'created',
            'last_action_at' => $this->db->now(),
            'updated_at' => $this->db->now(),
        ]);
        $this->log($user, 'service_action', 'service_control', 'Service "' . $data['name'] . '" registered (stopped).');

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('mock_services', $id);
        $this->requireRole($user, ['admin', 'operator']);

        if (array_key_exists('action', $data)) {
            return $this->performAction($row, (string) $data['action'], $user);
        }

        $updates = [];
        if (array_key_exists('name', $data)) {
            (new Validator())->string(['name' => $data['name']], 'name', 100, true)->throwIfInvalid();
            $updates['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('description', $data)) {
            (new Validator())->string(['description' => $data['description']], 'description', 300)->throwIfInvalid();
            $updates['description'] = trim((string) $data['description']);
        }
        if ($updates !== []) {
            $updates['updated_at'] = $this->db->now();
            $this->db->update('mock_services', $updates, ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    /** @param array<string, mixed> $row */
    private function performAction(array $row, string $action, array $user): array
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \App\ValidationException('Invalid service action.');
        }
        $current = (string) $row['status'];
        $allowed = match ($action) {
            'start' => in_array($current, ['stopped', 'down', 'unknown'], true),
            'stop' => in_array($current, ['running', 'starting', 'restarting', 'stopping'], true),
            'restart' => in_array($current, ['running', 'starting', 'restarting', 'stopping'], true),
            'inspect' => true,
        };
        if (!$allowed) {
            throw new \App\ValidationException(
                sprintf('Cannot %s service "%s" from current state "%s".', $action, $row['name'], $current)
            );
        }

        $next = match ($action) {
            'start' => 'running',
            'stop' => 'stopped',
            'restart' => 'running',
            'inspect' => $current,
        };
        $uptime = (int) $row['uptime_seconds'];
        if ($action === 'stop') {
            $uptime = 0;
        } elseif ($action === 'start' && $current === 'stopped') {
            $uptime = 1;
        } elseif ($action === 'restart') {
            $uptime = 1;
        }

        $this->db->update('mock_services', [
            'status' => $next,
            'uptime_seconds' => $uptime,
            'last_action' => $action,
            'last_action_at' => $this->db->now(),
            'updated_at' => $this->db->now(),
        ], ['id' => (int) $row['id']]);

        $this->log($user, 'service_action', 'service_control', sprintf(
            'Service "%s" %s by %s (%s -> %s).',
            $row['name'], $action, $user['username'], $current, $next
        ));

        return $this->item((int) $row['id'], $user);
    }

    public function delete(int $id, array $user): void
    {
        $this->requireRole($user, ['admin', 'operator']);
        $row = $this->requireRow('mock_services', $id);
        $this->db->delete('mock_services', ['id' => $id]);
        $this->log($user, 'service_action', 'service_control', 'Service "' . $row['name'] . '" removed.');
    }
}
