<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Audit;
use P13\Database;
use P13\Validation;

/**
 * Quarantine service (MAIL-09): review, release and delete quarantined
 * messages, scoped to the admin's domain.
 */
final class QuarantineService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function list(array $actor, string $status = '', string $q = ''): array
    {
        if ($actor['role'] === 'system_admin') {
            $where = ['1 = 1'];
            $params = [];
        } else {
            $where = ['domain_id = ?'];
            $params = [$actor['domain_id'] ?? null];
        }
        if (in_array($status, ['quarantined', 'released', 'deleted'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(from_address LIKE ? OR to_address LIKE ? OR subject LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $clause = implode(' AND ', $where);
        $items = $this->db->select(
            'SELECT q.*, d.name AS domain_name,
                    (SELECT u.username FROM users u WHERE u.id = q.reviewed_by) AS reviewed_by_username
               FROM quarantine q
               JOIN domains d ON d.id = q.domain_id
              WHERE ' . $clause . '
              ORDER BY q.id DESC
              LIMIT 200',
            $params
        );
        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * Release or delete a quarantined message.
     *
     * @return array{ok: bool, item?: array<string, mixed>, message?: string, errors?: array<string, string>}
     */
    public function action(array $actor, int $id, string $action): array
    {
        if (!Validation::in($action, ['release', 'delete'])) {
            return ['ok' => false, 'errors' => ['action' => 'Action must be release or delete.']];
        }
        $item = $this->scopedItem($actor, $id);
        if ($item === null) {
            return ['ok' => false, 'errors' => ['item' => 'Unknown or out-of-scope quarantine item.']];
        }
        $newStatus = $action === 'release' ? 'released' : 'deleted';
        $this->db->execute(
            'UPDATE quarantine SET status = ?, reviewed_by = ?, updated_at = datetime(\'now\') WHERE id = ?',
            [$newStatus, (int) $actor['id'], $id]
        );
        $this->audit->log((int) $actor['id'], (string) $actor['username'], (string) $actor['role'], 'quarantine.' . $action, 'Quarantine', (string) $id, [
            'subject' => $item['subject'],
            'from' => $item['from_address'],
        ]);
        $updated = $this->db->row('SELECT * FROM quarantine WHERE id = ?', [$id]);
        return [
            'ok' => true,
            'item' => $updated,
            'message' => $action === 'release' ? 'Quarantined message released.' : 'Quarantined message deleted.',
        ];
    }

    /**
     * Update a quarantine item (generic PATCH).
     *
     * @return array{ok: bool, item?: array<string, mixed>, errors?: array<string, string>}
     */
    public function update(array $actor, int $id, array $data): array
    {
        if (isset($data['action'])) {
            return $this->action($actor, $id, (string) $data['action']);
        }
        return ['ok' => false, 'errors' => ['action' => 'A quarantine action is required.']];
    }

    private function scopedItem(array $actor, int $id): ?array
    {
        if ($actor['role'] === 'system_admin') {
            return $this->db->row('SELECT * FROM quarantine WHERE id = ?', [$id]);
        }
        return $this->db->row(
            'SELECT * FROM quarantine WHERE id = ? AND domain_id = ?',
            [$id, $actor['domain_id'] ?? null]
        );
    }
}
