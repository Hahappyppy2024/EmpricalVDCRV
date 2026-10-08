<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class SiteRepository extends BaseRepository
{
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, d.name AS domain_name FROM sites s JOIN domains d ON d.id = s.domain_id WHERE s.user_id = ? ORDER BY s.id'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, d.name AS domain_name FROM sites s JOIN domains d ON d.id = s.domain_id WHERE s.id = ? AND s.user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $site = $stmt->fetch();

        return $site ?: null;
    }

    public function nameExistsForUser(string $name, int $userId, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM sites WHERE name = ? AND user_id = ? AND id != ?');
            $stmt->execute([$name, $userId, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM sites WHERE name = ? AND user_id = ?');
            $stmt->execute([$name, $userId]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(int $userId, int $domainId, string $name, string $documentRoot, string $status): int
    {
        $now = date('Y-m-d H:i:s');
        $deployedAt = $status === 'deployed' ? $now : null;
        $stmt = $this->db->prepare(
            'INSERT INTO sites (user_id, domain_id, name, document_root, status, deployed_at, created_at) VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $domainId, $name, $documentRoot, $status, $deployedAt, $now]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $domainId, string $name, string $documentRoot, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE sites SET domain_id = ?, name = ?, document_root = ?, status = ? WHERE id = ?');
        $stmt->execute([$domainId, $name, $documentRoot, $status, $id]);
    }

    public function setStatus(int $id, string $status, ?string $deployedAt = null): void
    {
        if ($deployedAt !== null) {
            $this->db->prepare('UPDATE sites SET status = ?, deployed_at = ? WHERE id = ?')->execute([$status, $deployedAt, $id]);
        } else {
            $this->db->prepare('UPDATE sites SET status = ? WHERE id = ?')->execute([$status, $id]);
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, d.name AS domain_name, u.username FROM sites s JOIN domains d ON d.id = s.domain_id JOIN users u ON u.id = s.user_id WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $site = $stmt->fetch();

        return $site ?: null;
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM sites WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
