<?php
declare(strict_types=1);
namespace App\Infrastructure;

final class Csrf
{
    public static function issue(?int $userId, int $ttl = 7200): array
    {
        $selector = bin2hex(random_bytes(12));
        $secret = bin2hex(random_bytes(24));
        $hash = hash('sha256', $secret);
        $expires = (new \DateTimeImmutable('+' . $ttl . ' seconds'))->format('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare('INSERT INTO csrf_tokens (selector, token_hash, user_id, expires_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$selector, $hash, $userId, $expires]);
        return [
            'selector' => $selector,
            'token' => $selector . '.' . $secret,
            'expires_at' => $expires,
        ];
    }

    public static function validate(?int $userId, ?string $token): bool
    {
        if (!$token || !str_contains($token, '.')) {
            return false;
        }
        [$selector, $secret] = explode('.', $token, 2);
        if ($selector === '' || $secret === '') {
            return false;
        }
        $stmt = Database::pdo()->prepare('SELECT token_hash, user_id, expires_at FROM csrf_tokens WHERE selector = ?');
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        if (strtotime($row['expires_at']) < time()) {
            return false;
        }
        if (!hash_equals($row['token_hash'], hash('sha256', $secret))) {
            return false;
        }
        $storedUser = $row['user_id'] !== null ? (int)$row['user_id'] : null;
        if ($storedUser !== null && $userId !== null && $storedUser !== $userId) {
            return false;
        }
        if ($storedUser === null && $userId !== null) {
            $stmt = Database::pdo()->prepare('UPDATE csrf_tokens SET user_id = ? WHERE selector = ?');
            $stmt->execute([$userId, $selector]);
        }
        return true;
    }
}