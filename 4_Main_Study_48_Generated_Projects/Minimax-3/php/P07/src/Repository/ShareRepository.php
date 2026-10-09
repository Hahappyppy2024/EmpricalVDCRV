<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;
use App\Infrastructure\Token;

final class ShareRepository
{
    public function create(array $data, string $plainToken): int
    {
        $selector = bin2hex(random_bytes(12));
        $hash = hash('sha256', $plainToken);
        $cipher = Token::encrypt($plainToken);
        $stmt = Database::pdo()->prepare('INSERT INTO shares (file_id, folder_id, created_by, token_selector, token_hash, scope, permission, expires_at, shared_with_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['file_id'] ?? null,
            $data['folder_id'] ?? null,
            $data['created_by'],
            $selector,
            $hash,
            $data['scope'],
            $data['permission'] ?? 'preview',
            $data['expires_at'] ?? null,
            $data['shared_with_user_id'] ?? null,
        ]);
        $id = (int)Database::pdo()->lastInsertId();
        return $id;
    }

    public function tokenCipher(int $id): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT token_hash FROM shares WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $row['token_hash'] : null;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shares WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySelector(string $selector): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shares WHERE token_selector = ?');
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $fields): void
    {
        $allowed = ['scope', 'permission', 'expires_at', 'revoked_at', 'shared_with_user_id'];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = "$key = ?";
                $params[] = $fields[$key];
            }
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        Database::pdo()->prepare('UPDATE shares SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    public function listForOwner(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shares WHERE created_by = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function listPrivateForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shares WHERE scope = \'private\' AND shared_with_user_id = ? AND revoked_at IS NULL ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function log(int $shareId, ?int $userId, string $action, bool $success): void
    {
        Database::pdo()->prepare('INSERT INTO sharing_links (share_id, user_id, action, success) VALUES (?, ?, ?, ?)')
            ->execute([$shareId, $userId, $action, $success ? 1 : 0]);
    }

    public function findByPlainToken(string $plain): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shares');
        $stmt->execute();
        $shares = $stmt->fetchAll();
        foreach ($shares as $share) {
            $expected = hash('sha256', $plain);
            if (hash_equals($share['token_hash'], $expected)) {
                return $share;
            }
        }
        return null;
    }
}