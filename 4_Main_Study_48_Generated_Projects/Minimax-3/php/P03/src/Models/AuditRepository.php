<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;

final class AuditRepository
{
    public static function log(?int $actorId, string $action, string $entityType, ?string $entityId, array $payload = []): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_events (actor_id, action, entity_type, entity_id, payload)
             VALUES (:a, :act, :e, :id, :p)'
        );
        $stmt->execute([
            ':a' => $actorId,
            ':act' => $action,
            ':e' => $entityType,
            ':id' => $entityId,
            ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function recent(int $limit = 50): array
    {
        $sql = 'SELECT a.*, u.display_name AS actor_name
                FROM audit_events a LEFT JOIN users u ON a.actor_id = u.id
                ORDER BY a.id DESC LIMIT ' . max(1, $limit);
        return Database::pdo()->query($sql)->fetchAll();
    }
}