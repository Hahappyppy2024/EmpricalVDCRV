<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AuditRepository extends BaseRepository
{
    public function record(?int $userId, string $username, string $action, string $module, string $entityType = '', string $entityId = '', string $details = ''): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_events (user_id, username, action, module, entity_type, entity_id, details, created_at) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $username, $action, $module, $entityType, $entityId, $details, date('Y-m-d H:i:s')]);
    }

    public function search(?string $module = null, ?string $action = null, ?string $username = null, ?int $limit = 200): array
    {
        $sql = 'SELECT * FROM audit_events WHERE 1 = 1';
        $params = [];
        if ($module !== null && $module !== '') {
            $sql .= ' AND module = ?';
            $params[] = $module;
        }
        if ($action !== null && $action !== '') {
            $sql .= ' AND action = ?';
            $params[] = $action;
        }
        if ($username !== null && $username !== '') {
            $sql .= ' AND username = ?';
            $params[] = $username;
        }
        $sql .= ' ORDER BY id DESC LIMIT ?';
        $params[] = max(1, $limit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function recent(int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT * FROM audit_events ORDER BY id DESC LIMIT ?');
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    }
}
