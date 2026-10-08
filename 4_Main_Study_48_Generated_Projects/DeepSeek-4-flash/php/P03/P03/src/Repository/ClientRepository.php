<?php

declare(strict_types=1);

namespace Shop\Repository;

final class ClientRepository extends Repository
{
    public function byId(int $id): ?array
    {
        return $this->row('SELECT * FROM api_clients WHERE id = ?', [$id]);
    }

    public function byIdForUser(int $id, int $userId): ?array
    {
        return $this->row('SELECT * FROM api_clients WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public function byToken(string $token): ?array
    {
        return $this->row('SELECT * FROM api_clients WHERE token = ?', [$token]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->rows('SELECT * FROM api_clients WHERE user_id = ? ORDER BY id', [$userId]);
    }

    public function create(?int $userId, string $name): int
    {
        $this->exec('INSERT INTO api_clients (user_id, name, token, prefs) VALUES (?, ?, ?, ?)', [
            $userId,
            $name,
            'client-' . bin2hex(random_bytes(12)),
            json_encode(['channel' => 'orders']),
        ]);
        return $this->insertId();
    }

    /**
     * @param array<string,mixed> $prefs
     */
    public function update(int $id, string $name, array $prefs): void
    {
        $this->exec('UPDATE api_clients SET name = ?, prefs = ? WHERE id = ?', [
            $name,
            json_encode($prefs, JSON_UNESCAPED_SLASHES),
            $id,
        ]);
    }
}
