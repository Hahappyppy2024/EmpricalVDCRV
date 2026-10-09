<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class AuditRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function search(array $filters = []): array
    {
        $sql = 'SELECT a.*, u.username AS actor_username FROM audit_events a LEFT JOIN users u ON u.id = a.actor_user_id WHERE 1=1';
        $params = [];
        if (!empty($filters['action'])) {
            $sql .= ' AND a.action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['actor_role'])) {
            $sql .= ' AND a.actor_role = ?';
            $params[] = $filters['actor_role'];
        }
        if (!empty($filters['target_type'])) {
            $sql .= ' AND a.target_type = ?';
            $params[] = $filters['target_type'];
        }
        if (!empty($filters['since'])) {
            $sql .= ' AND a.created_at >= ?';
            $params[] = $filters['since'];
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function all(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, u.username AS actor_username FROM audit_events a LEFT JOIN users u ON u.id = a.actor_user_id ORDER BY a.created_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function log(int $actorUserId, string $actorRole, string $action, string $targetType = null, string $targetId = null, string $detail = null, string $ip = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_events (actor_user_id, actor_role, action, target_type, target_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorUserId, $actorRole, $action, $targetType, $targetId, $detail, $ip]);
    }
}