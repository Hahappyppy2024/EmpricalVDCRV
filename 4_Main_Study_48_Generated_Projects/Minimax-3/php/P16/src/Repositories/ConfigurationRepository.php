<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class ConfigurationRepository
{
    public function all(): array
    {
        return Connection::get()->query('SELECT * FROM configuration_keys ORDER BY category, key')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM configuration_keys WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByKey(string $key): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM configuration_keys WHERE key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsert(string $key, string $value, string $category, string $description, ?int $actorId): void
    {
        $existing = $this->findByKey($key);
        if ($existing) {
            $stmt = Connection::get()->prepare(
                'UPDATE configuration_keys
                    SET value = :v,
                        category = :c,
                        description = :d,
                        updated_at = datetime(\'now\'),
                        updated_actor = :a,
                        pending_value = NULL,
                        pending_actor = NULL,
                        pending_at = NULL
                  WHERE id = :id'
            );
            $stmt->execute([
                ':v' => $value,
                ':c' => $category,
                ':d' => $description,
                ':a' => $actorId,
                ':id' => $existing['id'],
            ]);
        } else {
            $stmt = Connection::get()->prepare(
                'INSERT INTO configuration_keys (key, value, category, description, updated_actor)
                 VALUES (:k, :v, :c, :d, :a)'
            );
            $stmt->execute([
                ':k' => $key,
                ':v' => $value,
                ':c' => $category,
                ':d' => $description,
                ':a' => $actorId,
            ]);
        }
    }

    public function stagePending(int $id, string $pendingValue, int $actorId): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE configuration_keys
                SET pending_value = :p,
                    pending_actor = :a,
                    pending_at = datetime(\'now\')
              WHERE id = :id'
        );
        $stmt->execute([':p' => $pendingValue, ':a' => $actorId, ':id' => $id]);
    }

    public function approvePending(int $id): bool
    {
        $row = $this->findById($id);
        if (!$row || $row['pending_value'] === null) {
            return false;
        }
        $stmt = Connection::get()->prepare(
            'UPDATE configuration_keys
                SET value = :v,
                    updated_at = datetime(\'now\'),
                    updated_actor = :a,
                    pending_value = NULL,
                    pending_actor = NULL,
                    pending_at = NULL
              WHERE id = :id'
        );
        $stmt->execute([':v' => $row['pending_value'], ':a' => $row['pending_actor'], ':id' => $id]);
        return true;
    }

    public function rejectPending(int $id): bool
    {
        $row = $this->findById($id);
        if (!$row || $row['pending_value'] === null) {
            return false;
        }
        $stmt = Connection::get()->prepare(
            'UPDATE configuration_keys
                SET pending_value = NULL,
                    pending_actor = NULL,
                    pending_at = NULL
              WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        return true;
    }
}