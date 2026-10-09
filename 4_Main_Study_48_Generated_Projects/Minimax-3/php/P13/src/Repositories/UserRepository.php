<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, password_hash, full_name, role, domain_id, mailbox_quota_mb) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['full_name'] ?? $data['username'],
            $data['role'] ?? 'mail_user',
            $data['domain_id'] ?? null,
            $data['mailbox_quota_mb'] ?? 1024,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listForDomain(int $domainId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email, full_name, role, status, mailbox_quota_mb, mailbox_used_mb FROM users WHERE domain_id = ? ORDER BY username');
        $stmt->execute([$domainId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function updateQuota(int $id, int $quotaMb): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET mailbox_quota_mb = ? WHERE id = ?');
        return $stmt->execute([$quotaMb, $id]);
    }
}