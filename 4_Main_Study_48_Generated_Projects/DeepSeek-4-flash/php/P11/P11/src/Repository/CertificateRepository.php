<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class CertificateRepository extends BaseRepository
{
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, d.name AS domain_name FROM certificates c JOIN domains d ON d.id = c.domain_id WHERE c.user_id = ? ORDER BY c.id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, d.name AS domain_name FROM certificates c JOIN domains d ON d.id = c.domain_id WHERE c.id = ? AND c.user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $cert = $stmt->fetch();

        return $cert ?: null;
    }

    public function existsForDomain(int $userId, int $domainId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM certificates WHERE user_id = ? AND domain_id = ?');
        $stmt->execute([$userId, $domainId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(int $userId, int $domainId, string $provider, string $status, string $certText, string $keyText): int
    {
        $now = date('Y-m-d H:i:s');
        $notBefore = $status === 'issued' || $status === 'active' ? $now : null;
        $notAfter = $status === 'issued' || $status === 'active'
            ? date('Y-m-d H:i:s', time() + 90 * 86400)
            : null;
        $stmt = $this->db->prepare(
            'INSERT INTO certificates (user_id, domain_id, provider, status, certificate_text, private_key_text, not_before, not_after, created_at, renewed_at) VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $domainId, $provider, $status, $certText, $keyText, $notBefore, $notAfter, $now, $status === 'issued' || $status === 'active' ? $now : null]);

        return (int) $this->db->lastInsertId();
    }

    public function renew(int $id, string $status, string $certText, string $keyText): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'UPDATE certificates SET status = ?, certificate_text = ?, private_key_text = ?, not_before = ?, not_after = ?, renewed_at = ? WHERE id = ?'
        );
        $stmt->execute([$status, $certText, $keyText, $now, date('Y-m-d H:i:s', time() + 90 * 86400), $now, $id]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare('UPDATE certificates SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM certificates WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
