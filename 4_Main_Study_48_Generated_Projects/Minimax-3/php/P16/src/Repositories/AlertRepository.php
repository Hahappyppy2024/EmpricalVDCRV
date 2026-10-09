<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class AlertRepository
{
    public function all(?string $severity = null, ?string $state = null): array
    {
        $sql = 'SELECT a.*, au.username AS assignee_name, ab.username AS acknowledger_name, c.username AS creator_name
                  FROM alerts a
             LEFT JOIN users au ON au.id = a.assignee_id
             LEFT JOIN users ab ON ab.id = a.acknowledged_by
             LEFT JOIN users c  ON c.id  = a.assignee_id';
        $clauses = [];
        $params = [];
        if ($severity) {
            $clauses[] = 'a.severity = :sev';
            $params[':sev'] = $severity;
        }
        if ($state) {
            $clauses[] = 'a.state = :state';
            $params[':state'] = $state;
        }
        if ($clauses) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $sql .= ' ORDER BY a.created_at DESC';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT a.*, au.username AS assignee_name, ab.username AS acknowledger_name
               FROM alerts a
          LEFT JOIN users au ON au.id = a.assignee_id
          LEFT JOIN users ab ON ab.id = a.acknowledged_by
              WHERE a.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $severity, string $title, string $message, string $source): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO alerts (severity, title, message, source) VALUES (:s, :t, :m, :src)'
        );
        $stmt->execute([':s' => $severity, ':t' => $title, ':m' => $message, ':src' => $source]);
        return (int)Connection::get()->lastInsertId();
    }

    public function updateState(int $id, array $fields): bool
    {
        if (empty($fields)) {
            return false;
        }
        $set = [];
        $params = [':id' => $id];
        foreach ($fields as $k => $v) {
            $set[] = "$k = :$k";
            $params[":" . $k] = $v;
        }
        $set[] = 'updated_at = datetime(\'now\')';
        $sql = 'UPDATE alerts SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function comments(int $alertId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT c.*, u.username AS author_username
               FROM alert_comments c
               JOIN users u ON u.id = c.author_id
              WHERE c.alert_id = :aid
              ORDER BY c.created_at ASC'
        );
        $stmt->execute([':aid' => $alertId]);
        return $stmt->fetchAll();
    }

    public function addComment(int $alertId, int $authorId, string $body): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO alert_comments (alert_id, author_id, body) VALUES (:a, :u, :b)'
        );
        $stmt->execute([':a' => $alertId, ':u' => $authorId, ':b' => $body]);
        return (int)Connection::get()->lastInsertId();
    }
}