<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class DomainRepository extends BaseRepository
{
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE user_id = ? ORDER BY id');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function all(): array
    {
        $rows = $this->db->query('SELECT d.*, u.username FROM domains d JOIN users u ON u.id = d.user_id ORDER BY d.id')->fetchAll();

        return $rows;
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $domain = $stmt->fetch();

        return $domain ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$id]);
        $domain = $stmt->fetch();

        return $domain ?: null;
    }

    public function nameExistsForUser(string $name, int $userId, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM domains WHERE name = ? AND user_id = ? AND id != ?');
            $stmt->execute([$name, $userId, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM domains WHERE name = ? AND user_id = ?');
            $stmt->execute([$name, $userId]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(int $userId, string $name, string $kind, string $status): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO domains (user_id, name, kind, status, created_at) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$userId, $name, $kind, $status, date('Y-m-d H:i:s')]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $kind, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE domains SET name = ?, kind = ?, status = ? WHERE id = ?');
        $stmt->execute([$name, $kind, $status, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM domains WHERE id = ?')->execute([$id]);
    }

    public function dnsRecords(int $domainId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM dns_records WHERE domain_id = ? ORDER BY id');
        $stmt->execute([$domainId]);

        return $stmt->fetchAll();
    }

    public function addDns(int $domainId, string $type, string $name, string $value, int $ttl): int
    {
        $stmt = $this->db->prepare('INSERT INTO dns_records (domain_id, type, name, value, ttl) VALUES (?,?,?,?,?)');
        $stmt->execute([$domainId, $type, $name, $value, $ttl]);

        return (int) $this->db->lastInsertId();
    }

    public function deleteDns(int $dnsId, int $domainId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM dns_records WHERE id = ? AND domain_id = ?');

        return $stmt->execute([$dnsId, $domainId]);
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM domains WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
