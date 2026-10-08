<?php

declare(strict_types=1);

namespace Shop;

use PDO;

final class SessionManager
{
    public const COOKIE = 'shop_session';
    public const LIFETIME = 604800; // 7 days

    public function __construct(private PDO $pdo)
    {
    }

    public function start(int $userId, string $ip, string $userAgent): array
    {
        $token = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(16));
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (token, user_id, csrf_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $token,
            $userId,
            $csrf,
            $ip,
            mb_substr($userAgent, 0, 500),
            date('Y-m-d H:i:s', time() + self::LIFETIME),
        ]);
        $this->setCookie($token);
        return ['id' => (int) $this->pdo->lastInsertId(), 'token' => $token, 'csrf_token' => $csrf];
    }

    public function load(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id AS session_id, s.token AS session_token, s.csrf_token AS csrf_token, u.*, r.code AS role
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             JOIN roles r ON r.id = u.role_id
             WHERE s.token = ? AND s.expires_at > datetime(\'now\')'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function setCookie(string $token): void
    {
        setcookie(self::COOKIE, $token, [
            'expires' => time() + self::LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => true,
        ]);
    }

    public function destroy(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE token = ?');
        $stmt->execute([$token]);
        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => true]);
    }
}
