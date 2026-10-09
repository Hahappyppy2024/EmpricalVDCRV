<?php
declare(strict_types=1);
namespace App\Infrastructure;

final class Auth
{
    public const COOKIE = 'cfss';

    public static function start(int $userId, ?string $ip, ?string $ua): array
    {
        $selector = bin2hex(random_bytes(12));
        $secret = bin2hex(random_bytes(32));
        $hash = hash('sha256', $secret);
        $csrf = bin2hex(random_bytes(16));
        $lifetime = Config::int('SESSION_LIFETIME', 7200);
        $expires = (new \DateTimeImmutable('+' . $lifetime . ' seconds'))->format('Y-m-d H:i:s');
        Database::pdo()->prepare('INSERT INTO sessions (id, user_id, token_hash, csrf_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$selector, $userId, $hash, $csrf, $ip, $ua, $expires]);
        Database::pdo()->prepare('DELETE FROM csrf_tokens WHERE user_id IS NULL')->execute();
        return [
            'selector' => $selector,
            'token' => $selector . '.' . $secret,
            'csrf' => $csrf,
            'expires_at' => $expires,
        ];
    }

    public static function fromCookie(?string $cookie): ?array
    {
        if (!$cookie || !str_contains($cookie, '.')) {
            return null;
        }
        [$selector, $secret] = explode('.', $cookie, 2);
        $stmt = Database::pdo()->prepare('SELECT s.id AS sid, s.user_id, s.token_hash, s.csrf_token, s.expires_at, s.revoked_at, u.role, u.status, u.name, u.email FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if ($row['revoked_at']) {
            return null;
        }
        if (strtotime($row['expires_at']) < time()) {
            return null;
        }
        if (!hash_equals($row['token_hash'], hash('sha256', $secret))) {
            return null;
        }
        if ($row['status'] !== 'active') {
            return null;
        }
        return $row;
    }

    public static function destroy(string $selector): void
    {
        Database::pdo()->prepare('UPDATE sessions SET revoked_at = datetime(\'now\') WHERE id = ?')->execute([$selector]);
    }

    public static function cookieValue(string $token, int $ttl): array
    {
        return [
            self::COOKIE . '=' . $token,
            [
                'expires' => $ttl,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => Config::get('COOKIE_SECURE') === 'true',
            ],
        ];
    }

    public static function clearCookie(): array
    {
        return [
            self::COOKIE . '=',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => Config::get('COOKIE_SECURE') === 'true',
            ],
        ];
    }
}