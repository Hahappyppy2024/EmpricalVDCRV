<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Writes audit events that satisfy the SYS-12 use case.
 * Privileged actions across the application funnel through this service.
 */
final class AuditService
{
    public function record(
        ?int $actorId,
        string $actorName,
        string $action,
        string $target,
        array $detail = [],
        string $ipAddress = ''
    ): int {
        $stmt = Connection::get()->prepare(
            'INSERT INTO audit_events (actor_id, actor_name, action, target, detail, ip_address)
             VALUES (:actor_id, :actor_name, :action, :target, :detail, :ip)'
        );
        $stmt->execute([
            ':actor_id'   => $actorId,
            ':actor_name' => $actorName,
            ':action'     => $action,
            ':target'     => $target,
            ':detail'     => json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':ip'         => $ipAddress,
        ]);
        return (int)Connection::get()->lastInsertId();
    }

    public function recordFromSession(?array $session, string $action, string $target, array $detail = []): int
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($session === null) {
            return $this->record(null, 'anonymous', $action, $target, $detail, $ip);
        }
        return $this->record(
            (int)$session['user_id'],
            (string)$session['username'],
            $action,
            $target,
            $detail,
            $ip
        );
    }
}