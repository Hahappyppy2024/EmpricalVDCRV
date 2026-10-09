<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class ServiceRepository
{
    public function all(): array
    {
        return Connection::get()->query('SELECT * FROM services ORDER BY name')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM services WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, string $description, string $state = 'stopped'): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO services (name, description, state) VALUES (:n, :d, :s)'
        );
        $stmt->execute([':n' => $name, ':d' => $description, ':s' => $state]);
        return (int)Connection::get()->lastInsertId();
    }

    public function transition(int $id, string $newState, int $actorId, string $actionLabel): void
    {
        $startedAt = null;
        $pid = 0;
        if ($newState === 'running') {
            $startedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $pid = random_int(1000, 60000);
        }
        $stmt = Connection::get()->prepare(
            'UPDATE services
                SET state = :state,
                    pid = COALESCE(NULLIF(:pid, 0), pid),
                    started_at = COALESCE(:started, started_at),
                    last_action = :act,
                    last_actor_id = :actor,
                    updated_at = datetime(\'now\')
              WHERE id = :id'
        );
        $stmt->execute([
            ':state' => $newState,
            ':pid' => $pid,
            ':started' => $startedAt,
            ':act' => $actionLabel,
            ':actor' => $actorId,
            ':id' => $id,
        ]);
    }
}