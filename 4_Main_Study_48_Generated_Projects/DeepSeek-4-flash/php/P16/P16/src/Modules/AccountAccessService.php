<?php

declare(strict_types=1);

namespace App\Modules;

use App\AuditService;
use App\BaseService;
use App\Config;
use App\Database;
use App\Validator;

/**
 * SYS-01 Account access.
 *
 * Persistent entities: User, Session, AccountAccess.
 * Account access records track registrations, sign-ins, sign-outs and attempts.
 */
final class AccountAccessService extends BaseService
{
    public const ACTIONS = ['register', 'login', 'logout', 'password_reset', 'session_revoke', 'access_record'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT aa.*, u.username AS resolved_username, u.role AS user_role
                FROM account_access aa
                LEFT JOIN users u ON u.id = aa.user_id WHERE 1 = 1';
        $params = [];

        // Operators and viewers only ever see their own access history.
        if (($user['role'] ?? '') !== 'admin') {
            $sql .= ' AND (aa.user_id = :uid OR aa.username = :uname)';
            $params['uid'] = (int) ($user['id'] ?? 0);
            $params['uname'] = (string) ($user['username'] ?? '');
        }
        if (!empty($filters['action'])) {
            $sql .= ' AND aa.action = :action';
            $params['action'] = $filters['action'];
        }
        if (isset($filters['outcome']) && $filters['outcome'] !== '') {
            $sql .= ' AND aa.outcome = :outcome';
            $params['outcome'] = $filters['outcome'];
        }
        if (!empty($filters['username'])) {
            $sql .= ' AND aa.username LIKE :uname';
            $params['uname'] = '%' . $filters['username'] . '%';
        }
        $sql .= ' ORDER BY aa.id DESC LIMIT 200';

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        $row = $this->requireRow('account_access', $id, 'account_access.*');
        $this->assertScoped($row, $user);

        return $row;
    }

    public function create(array $data, array $user): array
    {
        $action = (string) ($data['action'] ?? 'access_record');
        $outcome = (string) ($data['outcome'] ?? 'success');
        $username = (string) ($data['username'] ?? ($user['username'] ?? ''));
        $details = (string) ($data['details'] ?? '');

        (new Validator())
            ->oneOf($data, 'action', self::ACTIONS, true)
            ->oneOf($data, 'outcome', ['success', 'failure'], true)
            ->string($data, 'details', 500)
            ->throwIfInvalid();

        $userId = (int) ($data['user_id'] ?? ($user['id'] ?? 0));
        if (($user['role'] ?? '') !== 'admin' && isset($data['user_id']) && (int) $data['user_id'] !== (int) $user['id']) {
            throw new \App\ForbiddenException('You can only record access for your own account.');
        }

        $id = $this->db->insert('account_access', [
            'user_id' => $userId ?: null,
            'username' => mb_substr($username, 0, 64),
            'action' => $action,
            'outcome' => $outcome,
            'ip_address' => mb_substr($this->ip($user), 0, 64),
            'details' => mb_substr($details, 0, 500),
            'created_at' => $this->db->now(),
        ]);

        $this->log($user, 'access_record', 'account_access', 'Access record #' . $id . ' created (' . $action . '/' . $outcome . ').');

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('account_access', $id);
        $this->assertScoped($row, $user);

        $updates = [];
        if (array_key_exists('outcome', $data)) {
            (new Validator())->oneOf($data, 'outcome', ['success', 'failure'], true)->throwIfInvalid();
            $updates['outcome'] = $data['outcome'];
        }
        if (array_key_exists('details', $data)) {
            (new Validator())->string($data, 'details', 500)->throwIfInvalid();
            $updates['details'] = mb_substr((string) $data['details'], 0, 500);
        }
        if ($updates !== []) {
            $this->db->update('account_access', $updates, ['id' => $id]);
        }

        $this->log($user, 'admin_operation', 'account_access', 'Access record #' . $id . ' updated.');

        return $this->item($id, $user);
    }

    public function delete(int $id, array $user): void
    {
        $row = $this->requireRow('account_access', $id);
        $this->assertScoped($row, $user);
        $this->db->delete('account_access', ['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function assertScoped(array $row, array $user): void
    {
        if (($user['role'] ?? '') === 'admin') {
            return;
        }
        $owned = (int) ($row['user_id'] ?? 0) === (int) ($user['id'] ?? 0)
            || ($row['username'] ?? '') === (string) ($user['username'] ?? '');
        if (!$owned) {
            throw new \App\NotFoundException('Record not found.');
        }
    }
}
