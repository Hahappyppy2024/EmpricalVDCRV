<?php

declare(strict_types=1);

namespace CloudFS\Repositories;

use CloudFS\Database\Database;

final class UserRepository
{
    public function __construct(private Database $db)
    {
    }

    public function findById(int $id): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public function listAll(): array
    {
        return $this->db->all('SELECT * FROM users ORDER BY username');
    }

    public function search(string $term): array
    {
        return $this->db->all(
            'SELECT * FROM users WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ? ORDER BY username LIMIT 50',
            ['%' . $term . '%', '%' . $term . '%', '%' . $term . '%']
        );
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM users');
    }
}
