<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class HealthCheckRepository
{
    public function all(?int $ownerId = null): array
    {
        $sql = 'SELECT h.*, u.username AS owner_username FROM health_check_targets h JOIN users u ON u.id = h.owner_id';
        $params = [];
        if ($ownerId !== null) {
            $sql .= ' WHERE h.owner_id = :oid';
            $params[':oid'] = $ownerId;
        }
        $sql .= ' ORDER BY h.created_at DESC';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM health_check_targets WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO health_check_targets (owner_id, name, kind, target, interval_sec, timeout_ms, state)
             VALUES (:o, :n, :k, :t, :i, :to, :state)'
        );
        $stmt->execute([
            ':o' => $data['owner_id'],
            ':n' => $data['name'],
            ':k' => $data['kind'],
            ':t' => $data['target'],
            ':i' => $data['interval_sec'],
            ':to' => $data['timeout_ms'],
            ':state' => 'unknown',
        ]);
        return (int)Connection::get()->lastInsertId();
    }

    public function update(int $id, array $fields): bool
    {
        $allowed = ['name', 'kind', 'target', 'interval_sec', 'timeout_ms'];
        $set = [];
        $params = [':id' => $id];
        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                continue;
            }
            $set[] = "$k = :$k";
            $params[":" . $k] = $v;
        }
        if (empty($set)) {
            return false;
        }
        $set[] = 'updated_at = datetime(\'now\')';
        $stmt = Connection::get()->prepare('UPDATE health_check_targets SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function recordCheck(int $id, string $state, string $error = ''): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE health_check_targets
                SET state = :state,
                    last_check_at = datetime(\'now\'),
                    last_error = :err,
                    updated_at = datetime(\'now\')
              WHERE id = :id'
        );
        $stmt->execute([':state' => $state, ':err' => $error, ':id' => $id]);
    }

    public function delete(int $id, ?int $ownerId = null): bool
    {
        $sql = 'DELETE FROM health_check_targets WHERE id = :id';
        $params = [':id' => $id];
        if ($ownerId !== null) {
            $sql .= ' AND owner_id = :oid';
            $params[':oid'] = $ownerId;
        }
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}