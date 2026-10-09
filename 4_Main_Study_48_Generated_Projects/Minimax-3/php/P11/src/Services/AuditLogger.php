<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;

final class AuditLogger
{
    public static function log(?int $actorId, ?string $actorRole, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null, ?string $ip = null): void
    {
        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO audit_events (actor_id, actor_role, action, target_type, target_id, details, ip) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$actorId, $actorRole, $action, $targetType, $targetId, $details, $ip]);
        } catch (\Throwable $e) {
            // audit log failure must not break user-facing requests
        }
    }
}