<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class QuarantineRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listForDomain(int $domainId, string $status = null): array
    {
        if ($status) {
            $stmt = $this->pdo->prepare('SELECT * FROM quarantined_messages WHERE domain_id = ? AND status = ? ORDER BY received_at DESC');
            $stmt->execute([$domainId, $status]);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM quarantined_messages WHERE domain_id = ? ORDER BY received_at DESC');
            $stmt->execute([$domainId]);
        }
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM quarantined_messages WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function resolve(int $id, int $actorId, string $decision): bool
    {
        $status = $decision === 'release' ? 'released' : 'deleted';
        $stmt = $this->pdo->prepare('UPDATE quarantined_messages SET status = ?, resolved_at = CURRENT_TIMESTAMP, resolved_by = ? WHERE id = ?');
        return $stmt->execute([$status, $actorId, $id]);
    }

    public function create(int $domainId, array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO quarantined_messages (domain_id, recipient, sender, subject, reason, status) VALUES (?, ?, ?, ?, ?, "quarantined")');
        $stmt->execute([$domainId, $data['recipient'], $data['sender'], $data['subject'] ?? null, $data['reason']]);
        return (int) $this->pdo->lastInsertId();
    }
}