<?php

declare(strict_types=1);

namespace Shop\Repository;

final class SearchRepository extends Repository
{
    public function byId(int $id): ?array
    {
        return $this->row('SELECT * FROM saved_searches WHERE id = ?', [$id]);
    }

    public function byIdForUser(int $id, int $userId): ?array
    {
        return $this->row('SELECT * FROM saved_searches WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->rows('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY id DESC', [$userId]);
    }

    public function create(int $userId, string $name, string $query, array $filters): int
    {
        $this->exec(
            'INSERT INTO saved_searches (user_id, name, query, filters) VALUES (?, ?, ?, ?)',
            [$userId, $name, $query, json_encode($filters, JSON_UNESCAPED_SLASHES)]
        );
        return $this->insertId();
    }

    public function update(int $id, string $name, string $query, array $filters): void
    {
        $this->exec(
            'UPDATE saved_searches SET name = ?, query = ?, filters = ? WHERE id = ?',
            [$name, $query, json_encode($filters, JSON_UNESCAPED_SLASHES), $id]
        );
    }
}
