<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-12 Audit logs and admin operations.
 *
 * Persistent entities: User, Session, AuditLogsAndAdminOperations, AuditEvent.
 * Admins review privileged operations and manage operators.
 */
final class AuditLogsAndAdminOperationsService extends BaseService
{
    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT ae.* FROM audit_events ae WHERE 1 = 1';
        $params = [];
        if (!empty($filters['module'])) {
            $sql .= ' AND ae.module = :module';
            $params['module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $sql .= ' AND ae.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['username'])) {
            $sql .= ' AND ae.username LIKE :username';
            $params['username'] = '%' . $filters['username'] . '%';
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND ae.created_at >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND ae.created_at <= :to';
            $params['to'] = $filters['to'];
        }
        $limit = min((int) ($filters['limit'] ?? 300), 1000);
        $sql .= ' ORDER BY ae.id DESC LIMIT ' . $limit;

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        return $this->requireRow('audit_events', $id);
    }

    public function create(array $data, array $user): array
    {
        $this->requireRole($user, ['admin']);
        $action = (string) ($data['action'] ?? '');

        if (in_array($action, ['operator_create', 'operator_toggle', 'operator_role'], true)) {
            return $this->manageOperator($data, $user);
        }

        $validator = (new Validator())
            ->required($data, 'action', 'detail')
            ->string(['action' => $data['action'] ?? ''], 'action', 64, true)
            ->string(['module' => $data['module'] ?? 'admin_operations'], 'module', 64)
            ->string(['detail' => $data['detail'] ?? ''], 'detail', 2000, true);
        $validator->throwIfInvalid();

        $id = $this->db->insert('audit_events', [
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'action' => $action,
            'module' => (string) ($data['module'] ?? 'admin_operations'),
            'detail' => (string) $data['detail'],
            'ip_address' => $this->ip($user),
            'created_at' => $this->db->now(),
        ]);

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $this->requireRole($user, ['admin']);
        $this->requireRow('audit_events', $id);

        $updates = [];
        if (array_key_exists('detail', $data)) {
            (new Validator())->string(['detail' => $data['detail']], 'detail', 2000)->throwIfInvalid();
            $updates['detail'] = (string) $data['detail'];
        }
        if ($updates !== []) {
            $this->db->update('audit_events', $updates, ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    public function delete(int $id, array $user): void
    {
        $this->requireRole($user, ['admin']);
        $this->requireRow('audit_events', $id);
        $this->db->delete('audit_events', ['id' => $id]);
    }

    public function operators(): array
    {
        return $this->db->fetchAll(
            'SELECT id, username, email, full_name, role, active, created_at FROM users ORDER BY id ASC'
        );
    }

    public function users(): array
    {
        return $this->db->fetchAll(
            'SELECT id, username, full_name, role, active FROM users WHERE active = 1 ORDER BY username ASC'
        );
    }

    /** @return array<string, mixed> */
    private function manageOperator(array $data, array $user): array
    {
        $action = (string) $data['action'];
        $detail = '';

        switch ($action) {
            case 'operator_create':
                $username = trim((string) ($data['username'] ?? ''));
                $email = trim((string) ($data['email'] ?? ''));
                $fullName = trim((string) ($data['full_name'] ?? $username));
                $password = (string) ($data['password'] ?? '');
                $role = (string) ($data['role'] ?? 'operator');
                if (!in_array($role, ['operator', 'viewer'], true)) {
                    $role = 'operator';
                }
                $validator = (new Validator())
                    ->required(['username' => $username, 'email' => $email, 'password' => $password], 'username', 'email', 'password')
                    ->string(['username' => $username], 'username', 50, true)
                    ->email(['email' => $email], 'email', true)
                    ->unique($this->db, 'users', 'username', $username)
                    ->unique($this->db, 'users', 'email', $email);
                if (mb_strlen($password) < 8) {
                    $validator->addError('password', 'Password must be at least 8 characters.');
                }
                $validator->throwIfInvalid();

                $id = $this->db->insert('users', [
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'full_name' => $fullName,
                    'role' => $role,
                    'active' => 1,
                    'created_at' => $this->db->now(),
                ]);
                $detail = 'Operator "' . $username . '" created with role ' . $role . '.';
                break;

            case 'operator_toggle':
                $operatorId = (int) ($data['operator_id'] ?? 0);
                $operator = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$operatorId]);
                if ($operator === null) {
                    throw new \App\NotFoundException('Operator not found.');
                }
                if ((int) $operator['id'] === (int) $user['id']) {
                    throw new \App\ValidationException('You cannot disable your own account.');
                }
                $active = (int) ($data['active'] ?? 0) === 1 ? 1 : 0;
                $this->db->update('users', ['active' => $active], ['id' => $operatorId]);
                $detail = 'Operator "' . $operator['username'] . '" ' . ($active ? 'enabled' : 'disabled') . '.';
                break;

            case 'operator_role':
                $operatorId = (int) ($data['operator_id'] ?? 0);
                $role = (string) ($data['role'] ?? '');
                if (!in_array($role, ['operator', 'viewer'], true)) {
                    throw new \App\ValidationException('Invalid role. Must be operator or viewer.');
                }
                $operator = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$operatorId]);
                if ($operator === null) {
                    throw new \App\NotFoundException('Operator not found.');
                }
                if ((int) $operator['id'] === (int) $user['id']) {
                    throw new \App\ValidationException('You cannot change your own role.');
                }
                $this->db->update('users', ['role' => $role], ['id' => $operatorId]);
                $detail = 'Role of "' . $operator['username'] . '" changed to ' . $role . '.';
                break;
        }

        $id = $this->db->insert('audit_events', [
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'action' => $action,
            'module' => 'audit_logs_and_admin_operations',
            'detail' => $detail,
            'ip_address' => $this->ip($user),
            'created_at' => $this->db->now(),
        ]);

        return $this->item($id, $user);
    }
}
