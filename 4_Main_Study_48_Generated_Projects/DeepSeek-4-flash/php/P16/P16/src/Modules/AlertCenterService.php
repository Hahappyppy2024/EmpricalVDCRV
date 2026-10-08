<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-09 Alert center.
 *
 * Persistent entities: User, Session, AlertCenter (alerts).
 * Operators view, acknowledge, assign and comment on alerts.
 */
final class AlertCenterService extends BaseService
{
    public const SEVERITIES = ['critical', 'warning', 'info'];
    public const STATUSES = ['open', 'acknowledged', 'assigned', 'closed'];
    public const ACTIONS = ['acknowledge', 'assign', 'close', 'comment'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT a.*, u.username AS assignee_name, c.username AS created_by_name
                FROM alerts a
                LEFT JOIN users u ON u.id = a.assignee_id
                LEFT JOIN users c ON c.id = a.created_by WHERE 1 = 1';
        $params = [];
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= ' AND a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['severity'])) {
            $sql .= ' AND a.severity = :severity';
            $params['severity'] = $filters['severity'];
        }
        $sql .= ' ORDER BY a.id DESC LIMIT 300';

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        return $this->db->fetchOne(
            'SELECT a.*, u.username AS assignee_name, c.username AS created_by_name
             FROM alerts a
             LEFT JOIN users u ON u.id = a.assignee_id
             LEFT JOIN users c ON c.id = a.created_by WHERE a.id = ?',
            [$id]
        ) ?? throw new \App\NotFoundException('Alert not found.');
    }

    public function create(array $data, array $user): array
    {
        (new Validator())
            ->required($data, 'title')
            ->string(['title' => $data['title'] ?? ''], 'title', 200, true)
            ->oneOf($data, 'severity', self::SEVERITIES, true)
            ->string(['source' => $data['source'] ?? 'system'], 'source', 100)
            ->throwIfInvalid();

        $id = $this->db->insert('alerts', [
            'title' => trim((string) $data['title']),
            'severity' => $data['severity'],
            'status' => 'open',
            'source' => trim((string) ($data['source'] ?? 'system')),
            'assignee_id' => null,
            'created_by' => (int) $user['id'],
            'comments' => '',
            'created_at' => $this->db->now(),
            'updated_at' => $this->db->now(),
        ]);

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('alerts', $id);

        if (array_key_exists('action', $data)) {
            return $this->performAction($row, (string) $data['action'], $data, $user);
        }

        if (array_key_exists('severity', $data)) {
            (new Validator())->oneOf($data, 'severity', self::SEVERITIES, true)->throwIfInvalid();
            $this->db->update('alerts', ['severity' => $data['severity'], 'updated_at' => $this->db->now()], ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    /** @param array<string, mixed> $row */
    private function performAction(array $row, string $action, array $data, array $user): array
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \App\ValidationException('Invalid alert action.');
        }
        $updates = [];
        $comment = trim((string) ($data['comment'] ?? ''));

        switch ($action) {
            case 'acknowledge':
                $updates['status'] = 'acknowledged';
                break;
            case 'assign':
                $assigneeId = (int) ($data['assignee_id'] ?? 0);
                $assignee = $this->db->fetchOne('SELECT id, username FROM users WHERE id = ? AND active = 1', [$assigneeId]);
                if ($assignee === null) {
                    throw new \App\ValidationException('Assignee user not found.');
                }
                $updates['assignee_id'] = $assigneeId;
                $updates['status'] = 'assigned';
                $comment = $comment !== '' ? $comment : 'Assigned to ' . $assignee['username'] . '.';
                break;
            case 'close':
                $updates['status'] = 'closed';
                break;
            case 'comment':
                break;
        }

        $append = '';
        if ($comment !== '') {
            $append = "\n" . '[' . date('Y-m-d H:i:s') . '] ' . $user['username'] . ': ' . $comment;
        }
        $comments = (string) $row['comments'] . $append;
        $updates['comments'] = mb_substr($comments, 0, 5000);
        $updates['updated_at'] = $this->db->now();
        $this->db->update('alerts', $updates, ['id' => (int) $row['id']]);
        $this->log($user, 'alert_action', 'alert_center', 'Alert "' . $row['title'] . '" ' . $action . '.');

        return $this->item((int) $row['id'], $user);
    }

    public function delete(int $id, array $user): void
    {
        $this->requireRow('alerts', $id);
        $this->db->delete('alerts', ['id' => $id]);
    }
}
