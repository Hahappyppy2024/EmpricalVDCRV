<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class DomainRepository
{
    public function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT d.*, (SELECT COUNT(*) FROM dns_records r WHERE r.domain_id = d.id) AS record_count
             FROM domains d WHERE d.user_id = ? ORDER BY d.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        return Database::pdo()->query(
            'SELECT d.*, u.username FROM domains d JOIN users u ON u.id = d.user_id ORDER BY d.id DESC'
        )->fetchAll();
    }

    public function findOwned(int $id, int $userId, ?string $role = null): ?array
    {
        $sql = 'SELECT * FROM domains WHERE id = ?';
        $params = [$id];
        if ($role !== 'admin') {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function existsForUser(int $userId, string $domain): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM domains WHERE user_id = ? AND domain = ?');
        $stmt->execute([$userId, $domain]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(int $userId, string $domain, string $type, string $documentRoot): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO domains (user_id, domain, type, document_root, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $domain, $type, $documentRoot, 'active']);
        return (int)Database::pdo()->lastInsertId();
    }

    public function updateStatus(int $id, int $userId, string $status): void
    {
        $stmt = Database::pdo()->prepare('UPDATE domains SET status = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$status, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM domains WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    public function recordsFor(int $domainId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM dns_records WHERE domain_id = ? ORDER BY id');
        $stmt->execute([$domainId]);
        return $stmt->fetchAll();
    }

    public function addRecord(int $domainId, string $name, string $type, string $value, int $ttl): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dns_records (domain_id, name, type, value, ttl) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$domainId, $name, $type, $value, $ttl]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function deleteRecord(int $recordId, int $domainId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM dns_records WHERE id = ? AND domain_id = ?');
        $stmt->execute([$recordId, $domainId]);
    }
}