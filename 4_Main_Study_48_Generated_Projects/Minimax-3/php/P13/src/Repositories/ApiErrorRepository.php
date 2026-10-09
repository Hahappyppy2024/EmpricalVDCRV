<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class ApiErrorRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function list(): array
    {
        $stmt = $this->pdo->query('SELECT e.*, u.username FROM api_errors e LEFT JOIN users u ON u.id = e.user_id ORDER BY e.created_at DESC LIMIT 100');
        return $stmt->fetchAll();
    }

    public function log(?int $userId, string $endpoint, int $code, string $state, ?string $message = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO api_errors (user_id, endpoint, error_code, error_state, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $endpoint, $code, $state, $message]);
        return (int) $this->pdo->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM api_errors WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function resolve(int $id, string $state): bool
    {
        $stmt = $this->pdo->prepare('UPDATE api_errors SET error_state = ?, message = COALESCE(message, "") || ? WHERE id = ?');
        return $stmt->execute([$state, ' [resolved]', $id]);
    }
}