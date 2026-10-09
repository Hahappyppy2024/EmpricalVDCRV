<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class SearchRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO searches (user_id, name, query, owner, tag, from_date, to_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['user_id'],
            $data['name'],
            $data['query'] ?? null,
            $data['owner'] ?? null,
            $data['tag'] ?? null,
            $data['from_date'] ?? null,
            $data['to_date'] ?? null,
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM searches WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM searches WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM searches WHERE id = ?')->execute([$id]);
    }
}