<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class AuditEventRepository
{
    public function all(?string $action = null, ?string $actorName = null, int $limit = 200): array
    {
        $sql = 'SELECT a.*, u.username AS linked_username FROM audit_events a LEFT JOIN users u ON u.id = a.actor_id';
        $clauses = [];
        $params = [];
        if ($action) {
            $clauses[] = 'a.action = :action';
            $params[':action'] = $action;
        }
        if ($actorName) {
            $clauses[] = '(a.actor_name LIKE :aname OR u.username LIKE :aname)';
            $params[':aname'] = '%' . $actorName . '%';
        }
        if ($clauses) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT ' . max(1, min(1000, $limit));
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function recent(int $limit = 50): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT a.*, u.username AS linked_username FROM audit_events a LEFT JOIN users u ON u.id = a.actor_id
              ORDER BY a.created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}