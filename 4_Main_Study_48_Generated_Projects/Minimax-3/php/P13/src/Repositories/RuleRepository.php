<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class RuleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listFor(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rules WHERE user_id = ? ORDER BY priority ASC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function find(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rules WHERE user_id = ? AND id = ?');
        $stmt->execute([$userId, $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO rules (user_id, name, conditions, actions, priority, enabled) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $data['name'],
            $data['conditions'],
            $data['actions'],
            $data['priority'] ?? 100,
            isset($data['enabled']) ? (int) (bool) $data['enabled'] : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $userId, int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE rules SET name = ?, conditions = ?, actions = ?, priority = ?, enabled = ? WHERE user_id = ? AND id = ?');
        return $stmt->execute([
            $data['name'],
            $data['conditions'],
            $data['actions'],
            $data['priority'] ?? 100,
            isset($data['enabled']) ? (int) (bool) $data['enabled'] : 1,
            $userId,
            $id,
        ]);
    }

    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM rules WHERE user_id = ? AND id = ?');
        return $stmt->execute([$userId, $id]);
    }
}