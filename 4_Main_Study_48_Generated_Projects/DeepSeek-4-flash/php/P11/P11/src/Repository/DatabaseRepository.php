<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class DatabaseRepository extends BaseRepository
{
    public function allForUser(int $userId): array
    {
        $rows = $this->db->query('SELECT * FROM databases')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            if ((int) $row['user_id'] !== $userId) {
                continue;
            }
            $row['user_count'] = count($this->usersFor((int) $row['id']));
            $result[] = $row;
        }

        return $result;
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM databases WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $db = $stmt->fetch();

        return $db ?: null;
    }

    public function nameExistsForUser(string $name, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM databases WHERE name = ? AND user_id = ?');
        $stmt->execute([$name, $userId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(int $userId, string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO databases (user_id, name, created_at) VALUES (?,?,?)');
        $stmt->execute([$userId, $name, date('Y-m-d H:i:s')]);

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM databases WHERE id = ?')->execute([$id]);
    }

    public function usersFor(int $databaseId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM database_users WHERE database_id = ? ORDER BY id');
        $stmt->execute([$databaseId]);

        return $stmt->fetchAll();
    }

    public function addUser(int $databaseId, string $username, string $passwordHash, string $host, string $privileges): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO database_users (database_id, username, password_hash, host, privileges) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$databaseId, $username, $passwordHash, $host, $privileges]);

        return (int) $this->db->lastInsertId();
    }

    public function findUser(int $userId, int $databaseId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT du.* FROM database_users du JOIN databases d ON d.id = du.database_id WHERE du.id = ? AND d.user_id = ?'
        );
        $stmt->execute([$userId, $databaseId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function deleteUser(int $userId): void
    {
        $this->db->prepare('DELETE FROM database_users WHERE id = ?')->execute([$userId]);
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM databases WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
