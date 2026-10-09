<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class AuditRepository
{
    public function __construct(private PDO $pdo) {}

    public function record(int $actorId, string $action, string $targetType, ?int $targetId = null, array $payload = []): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_events (actor_id, action, target_type, target_id, payload_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$actorId, $action, $targetType, $targetId, json_encode($payload)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function recent(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.full_name AS actor_name FROM audit_events a
             LEFT JOIN users u ON u.id = a.actor_id
             ORDER BY a.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
