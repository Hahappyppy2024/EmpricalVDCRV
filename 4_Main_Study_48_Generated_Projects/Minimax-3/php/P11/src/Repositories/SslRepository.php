<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class SslRepository
{
    public function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.*, d.domain FROM certificates c JOIN domains d ON d.id = c.domain_id WHERE c.user_id = ? ORDER BY c.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM certificates WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function existsForDomain(int $domainId, string $commonName): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM certificates WHERE domain_id = ? AND common_name = ?');
        $stmt->execute([$domainId, $commonName]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(int $userId, int $domainId, string $cn, string $issuer, string $cert, string $key, string $validFrom, string $validTo, string $status): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO certificates (user_id, domain_id, common_name, issuer, cert_pem, key_pem, valid_from, valid_to, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $domainId, $cn, $issuer, $cert, $key, $validFrom, $validTo, $status]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function revoke(int $id, int $userId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE certificates SET status = ? WHERE id = ? AND user_id = ?');
        $stmt->execute(['revoked', $id, $userId]);
    }

    public function renew(int $id, int $userId, string $validTo): void
    {
        $stmt = Database::pdo()->prepare('UPDATE certificates SET valid_to = ?, status = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$validTo, 'active', $id, $userId]);
    }
}