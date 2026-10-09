<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class SiteRepository
{
    public function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.*, d.domain FROM sites s LEFT JOIN domains d ON d.id = s.domain_id WHERE s.user_id = ? ORDER BY s.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sites WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function create(int $userId, ?int $domainId, string $siteName, string $documentRoot, string $phpVersion): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sites (user_id, domain_id, site_name, document_root, php_version, status) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $domainId, $siteName, $documentRoot, $phpVersion, 'deployed']);
        return (int)Database::pdo()->lastInsertId();
    }

    public function update(int $id, int $userId, ?int $domainId, string $siteName, string $documentRoot, string $phpVersion, string $status): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE sites SET domain_id = ?, site_name = ?, document_root = ?, php_version = ?, status = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$domainId, $siteName, $documentRoot, $phpVersion, $status, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM sites WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}