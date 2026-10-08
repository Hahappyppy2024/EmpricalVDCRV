<?php

declare(strict_types=1);

namespace Shop\Repository;

final class BroadcastRepository extends Repository
{
    public function add(string $event, array $payload, string $channel = 'orders'): int
    {
        $this->exec(
            'INSERT INTO broadcasts (channel, event, payload) VALUES (?, ?, ?)',
            [$channel, $event, json_encode($payload, JSON_UNESCAPED_SLASHES)]
        );
        return $this->insertId();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function after(int $lastId, int $limit = 100): array
    {
        return $this->rows(
            'SELECT * FROM broadcasts WHERE id > ? ORDER BY id LIMIT ?',
            [$lastId, $limit]
        );
    }

    public function latestId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM broadcasts');
        return (int) $stmt->fetchColumn();
    }
}
