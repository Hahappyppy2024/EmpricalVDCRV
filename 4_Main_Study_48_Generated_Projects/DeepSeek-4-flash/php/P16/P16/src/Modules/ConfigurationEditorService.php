<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-08 Configuration editor.
 *
 * Persistent entities: User, Session, ConfigurationEditor (config keys).
 * Admins edit approved configuration keys and review pending changes; every
 * privileged change is audited.
 */
final class ConfigurationEditorService extends BaseService
{
    public const STATUSES = ['active', 'pending'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT ck.*, u.username AS updated_by_name
                FROM config_keys ck LEFT JOIN users u ON u.id = ck.updated_by WHERE 1 = 1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND ck.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (ck.config_key LIKE :q OR ck.description LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        $sql .= ' ORDER BY ck.config_key ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        return $this->db->fetchOne(
            'SELECT ck.*, u.username AS updated_by_name
             FROM config_keys ck LEFT JOIN users u ON u.id = ck.updated_by WHERE ck.id = ?',
            [$id]
        ) ?? throw new \App\NotFoundException('Config key not found.');
    }

    public function create(array $data, array $user): array
    {
        $this->requireRole($user, ['admin']);
        $key = trim((string) ($data['config_key'] ?? ''));
        (new Validator())
            ->required($data, 'config_key', 'config_value')
            ->string(['config_key' => $key], 'config_key', 120, true)
            ->string(['description' => $data['description'] ?? ''], 'description', 300)
            ->unique($this->db, 'config_keys', 'config_key', $key)
            ->throwIfInvalid();

        $id = $this->db->insert('config_keys', [
            'config_key' => $key,
            'config_value' => (string) $data['config_value'],
            'description' => trim((string) ($data['description'] ?? '')),
            'status' => 'active',
            'pending_value' => null,
            'updated_by' => (int) $user['id'],
            'updated_at' => $this->db->now(),
        ]);
        $this->log($user, 'config_change', 'configuration_editor', 'Config key "' . $key . '" created.');

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $this->requireRole($user, ['admin']);
        $row = $this->requireRow('config_keys', $id);

        if (array_key_exists('action', $data)) {
            return $this->reviewChange($row, (string) $data['action'], $user);
        }

        if (array_key_exists('config_value', $data)) {
            // Submitting a new value creates a pending change for review.
            $this->db->update('config_keys', [
                'pending_value' => (string) $data['config_value'],
                'status' => 'pending',
                'updated_by' => (int) $user['id'],
                'updated_at' => $this->db->now(),
            ], ['id' => $id]);
            $this->log($user, 'config_change', 'configuration_editor', 'Pending change proposed for "' . $row['config_key'] . '".');
        }
        if (array_key_exists('description', $data)) {
            (new Validator())->string(['description' => $data['description']], 'description', 300)->throwIfInvalid();
            $this->db->update('config_keys', ['description' => trim((string) $data['description'])], ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    /** @param array<string, mixed> $row */
    private function reviewChange(array $row, string $action, array $user): array
    {
        switch ($action) {
            case 'approve':
                if ($row['status'] !== 'pending' || $row['pending_value'] === null) {
                    throw new \App\ValidationException('There is no pending change to approve.');
                }
                $this->db->update('config_keys', [
                    'config_value' => $row['pending_value'],
                    'pending_value' => null,
                    'status' => 'active',
                    'updated_by' => (int) $user['id'],
                    'updated_at' => $this->db->now(),
                ], ['id' => (int) $row['id']]);
                $this->log($user, 'config_approve', 'configuration_editor', 'Pending change approved for "' . $row['config_key'] . '".');
                break;
            case 'reject':
                $this->db->update('config_keys', [
                    'pending_value' => null,
                    'status' => 'active',
                    'updated_by' => (int) $user['id'],
                    'updated_at' => $this->db->now(),
                ], ['id' => (int) $row['id']]);
                $this->log($user, 'config_approve', 'configuration_editor', 'Pending change rejected for "' . $row['config_key'] . '".');
                break;
            default:
                throw new \App\ValidationException('Invalid review action.');
        }

        return $this->item((int) $row['id'], $user);
    }

    public function delete(int $id, array $user): void
    {
        $this->requireRole($user, ['admin']);
        $row = $this->requireRow('config_keys', $id);
        $this->db->delete('config_keys', ['id' => $id]);
        $this->log($user, 'config_change', 'configuration_editor', 'Config key "' . $row['config_key'] . '" deleted.');
    }
}
