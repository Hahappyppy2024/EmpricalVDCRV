<?php

declare(strict_types=1);

namespace Shop\Repository;

final class AddressRepository extends Repository
{
    /**
     * @return list<array<string,mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->rows('SELECT * FROM addresses WHERE user_id = ? ORDER BY id', [$userId]);
    }

    public function byId(int $id): ?array
    {
        return $this->row('SELECT * FROM addresses WHERE id = ?', [$id]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(int $userId, array $data): int
    {
        $this->exec(
            'INSERT INTO addresses (user_id, label, line1, line2, city, postal_code, country, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $data['label'] ?? 'Home',
                $data['line1'],
                $data['line2'] ?? '',
                $data['city'],
                $data['postal_code'],
                $data['country'] ?? 'US',
                $data['phone'] ?? '',
            ]
        );
        return $this->insertId();
    }

    public function delete(int $id): void
    {
        $this->exec('DELETE FROM addresses WHERE id = ?', [$id]);
    }
}
