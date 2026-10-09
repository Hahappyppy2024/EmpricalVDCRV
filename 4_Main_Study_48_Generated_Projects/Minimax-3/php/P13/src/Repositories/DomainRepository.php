<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class DomainRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM domains ORDER BY name');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM domains WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO domains (name, description, max_mailboxes, max_quota_mb) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['max_mailboxes'] ?? 50,
            $data['max_quota_mb'] ?? 5120,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE domains SET description = ?, max_mailboxes = ?, max_quota_mb = ?, status = ? WHERE id = ?');
        return $stmt->execute([
            $data['description'] ?? null,
            $data['max_mailboxes'] ?? 50,
            $data['max_quota_mb'] ?? 5120,
            $data['status'] ?? 'active',
            $id,
        ]);
    }

    public function aliasesFor(int $domainId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM aliases WHERE domain_id = ? ORDER BY source');
        $stmt->execute([$domainId]);
        return $stmt->fetchAll();
    }

    public function createAlias(int $domainId, string $source, string $destination): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO aliases (domain_id, source, destination) VALUES (?, ?, ?)');
        $stmt->execute([$domainId, $source, $destination]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteAlias(int $domainId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM aliases WHERE domain_id = ? AND id = ?');
        return $stmt->execute([$domainId, $id]);
    }
}