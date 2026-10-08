<?php

declare(strict_types=1);

namespace P13;

/**
 * Audit event service used by MAIL-06, MAIL-08 and MAIL-10.
 * Every privileged action is recorded in audit_events.
 */
final class Audit
{
    public function __construct(private Database $db)
    {
    }

    public function log(
        ?int $userId,
        ?string $actorUsername,
        ?string $actorRole,
        string $action,
        string $entityType,
        ?string $entityId = null,
        array $details = [],
        ?string $ip = null
    ): void {
        $this->db->execute(
            'INSERT INTO audit_events (user_id, actor_username, actor_role, action, entity_type, entity_id, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [
                $userId,
                $actorUsername,
                $actorRole ?? 'guest',
                $action,
                $entityType,
                $entityId,
                json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $ip ?? '127.0.0.1',
            ]
        );
    }

    /**
     * Query audit events with bounded, deterministic filters.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function query(array $filters, int $limit = 100): array
    {
        $where = [];
        $params = [];

        if (isset($filters['action']) && $filters['action'] !== '') {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        if (isset($filters['entity_type']) && $filters['entity_type'] !== '') {
            $where[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (isset($filters['role']) && $filters['role'] !== '') {
            $where[] = 'actor_role = ?';
            $params[] = $filters['role'];
        }
        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $where[] = '(actor_username LIKE ? OR entity_id LIKE ? OR details LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
        if (isset($filters['from']) && $filters['from'] !== '') {
            $where[] = 'created_at >= ?';
            $params[] = (string) $filters['from'];
        }
        if (isset($filters['to']) && $filters['to'] !== '') {
            $where[] = 'created_at <= ?';
            $params[] = (string) $filters['to'];
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $limit = max(1, min(500, $limit));

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM audit_events' . $clause, $params);
        $items = $this->db->select(
            'SELECT id, user_id, actor_username, actor_role, action, entity_type, entity_id, details, ip_address, created_at
               FROM audit_events' . $clause . ' ORDER BY id DESC LIMIT ?',
            array_merge($params, [$limit])
        );

        foreach ($items as &$item) {
            $item['details'] = json_decode((string) $item['details'], true) ?: [];
        }
        unset($item);

        return ['items' => $items, 'total' => $total];
    }
}
