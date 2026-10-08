<?php

declare(strict_types=1);

namespace Shop\Repository;

final class AuditRepository extends Repository
{
    public function log(?int $userId, string $action, string $entityType = '', ?int $entityId = null, string $details = ''): int
    {
        $this->exec(
            'INSERT INTO audit_events (user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)',
            [$userId, $action, $entityType, $entityId, $details]
        );
        return $this->insertId();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recent(int $limit = 200): array
    {
        return $this->rows(
            'SELECT a.*, u.name AS actor_name, u.email AS actor_email
             FROM audit_events a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT ?',
            [$limit]
        );
    }
}
