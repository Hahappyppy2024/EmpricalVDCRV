<?php
declare(strict_types=1);
namespace App\Infrastructure;

final class Audit
{
    public static function log(?int $userId, string $action, string $entityType, ?int $entityId, array $details = [], ?string $ip = null): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO audit_events (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            $ip,
        ]);
    }
}