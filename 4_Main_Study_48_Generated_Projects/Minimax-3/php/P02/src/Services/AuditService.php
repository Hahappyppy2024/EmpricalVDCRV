<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;

final class AuditService
{
    public static function log(?int $actorId, string $action, string $targetType, string $targetId, string $details = ''): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO audit_events (actor_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, $targetType, $targetId, $details]);
    }

    public static function list(int $limit = 100): array
    {
        $stmt = Database::pdo()->prepare('SELECT a.*, u.username FROM audit_events a LEFT JOIN users u ON u.id = a.actor_id ORDER BY a.id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}