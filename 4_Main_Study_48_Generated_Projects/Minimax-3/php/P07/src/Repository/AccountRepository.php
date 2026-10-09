<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class AccountRepository
{
    public function record(?int $userId, string $action, string $status, array $details = [], ?string $ip = null): void
    {
        Database::pdo()->prepare('INSERT INTO account_access (user_id, action, status, details, ip_address) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $action, $status, json_encode($details, JSON_UNESCAPED_UNICODE), $ip]);
    }

    public function createRecovery(int $userId): array
    {
        $selector = bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(16));
        $hash = hash('sha256', $secret);
        $expires = (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare('INSERT INTO account_recoveries (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $selector, $hash, $expires]);
        $id = (int)Database::pdo()->lastInsertId();
        return ['id' => $id, 'selector' => $selector, 'token' => $selector . '.' . $secret, 'expires_at' => $expires];
    }

    public function findRecovery(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM account_recoveries WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markRecoveryUsed(int $id): void
    {
        Database::pdo()->prepare('UPDATE account_recoveries SET used_at = datetime(\'now\') WHERE id = ?')->execute([$id]);
    }

    public function deleteUnused(): void
    {
        Database::pdo()->prepare('DELETE FROM account_recoveries WHERE used_at IS NOT NULL')->execute();
    }
}