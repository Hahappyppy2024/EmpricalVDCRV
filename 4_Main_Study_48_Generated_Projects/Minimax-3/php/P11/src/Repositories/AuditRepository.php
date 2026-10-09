<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class AuditRepository
{
    public function listFiltered(array $filters, string $role, int $userId): array
    {
        $where = [];
        $params = [];
        if ($role !== 'admin') {
            $where[] = '(actor_id = ? OR actor_role = ?)';
            $params[] = $userId;
            $params[] = 'customer';
        }
        if (!empty($filters['action'])) {
            $where[] = 'action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['target_type'])) {
            $where[] = 'target_type = ?';
            $params[] = $filters['target_type'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to'];
        }
        $sql = 'SELECT a.*, u.username FROM audit_events a LEFT JOIN users u ON u.id = a.actor_id';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY a.id DESC LIMIT 200';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findOwned(int $id, int $userId, string $role): ?array
    {
        $sql = 'SELECT * FROM audit_events WHERE id = ?';
        $params = [$id];
        if ($role !== 'admin') {
            $sql .= ' AND actor_id = ?';
            $params[] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function append(int $actorId, string $actorRole, string $action, ?string $targetType, ?int $targetId, ?string $details, ?string $ip): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_events (actor_id, actor_role, action, target_type, target_id, details, ip) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$actorId, $actorRole, $action, $targetType, $targetId, $details, $ip]);
        return (int)Database::pdo()->lastInsertId();
    }
}