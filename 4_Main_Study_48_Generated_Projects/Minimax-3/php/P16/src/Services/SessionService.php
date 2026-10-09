<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use App\Database\Connection;

/**
 * Server-side session store backed by SQLite.
 * The cookie only carries the opaque session_id; all user data lives in the DB.
 */
final class SessionService
{
    private const COOKIE_NAME = 'p16_session';

    public function __construct(
        private int $lifetimeMinutes = 120
    ) {}

    public function cookieName(): string
    {
        return self::COOKIE_NAME;
    }

    public function start(): ?array
    {
        $sessionId = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!is_string($sessionId) || $sessionId === '') {
            return null;
        }
        return $this->load($sessionId);
    }

    public function login(int $userId, string $ipAddress, string $userAgent): string
    {
        $sessionId = $this->generateId();
        $expires = (new \DateTimeImmutable('+' . $this->lifetimeMinutes . ' minutes'))
            ->format('Y-m-d H:i:s');
        $stmt = Connection::get()->prepare(
            'INSERT INTO sessions (session_id, user_id, ip_address, user_agent, payload, expires_at)
             VALUES (:sid, :uid, :ip, :ua, :payload, :expires)'
        );
        $stmt->execute([
            ':sid' => $sessionId,
            ':uid' => $userId,
            ':ip'  => $ipAddress,
            ':ua'  => $userAgent,
            ':payload' => '{}',
            ':expires' => $expires,
        ]);
        $this->setCookie($sessionId);
        return $sessionId;
    }

    public function logout(?string $sessionId = null): void
    {
        $sessionId ??= $_COOKIE[self::COOKIE_NAME] ?? null;
        if (is_string($sessionId) && $sessionId !== '') {
            $stmt = Connection::get()->prepare('DELETE FROM sessions WHERE session_id = :sid');
            $stmt->execute([':sid' => $sessionId]);
        }
        $this->clearCookie();
    }

    public function load(string $sessionId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT s.id AS sid_pk, s.session_id, s.user_id, s.expires_at, s.payload,
                    u.username, u.role, u.full_name, u.enabled
               FROM sessions s
               JOIN users u ON u.id = s.user_id
              WHERE s.session_id = :sid
              LIMIT 1'
        );
        $stmt->execute([':sid' => $sessionId]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->clearCookie();
            return null;
        }
        $now = new \DateTimeImmutable('now');
        $exp = new \DateTimeImmutable((string)$row['expires_at']);
        if ($now > $exp || (int)$row['enabled'] !== 1) {
            $this->logout($sessionId);
            return null;
        }
        return [
            'session_id' => (string)$row['session_id'],
            'user_id'    => (int)$row['user_id'],
            'username'   => (string)$row['username'],
            'role'       => (string)$row['role'],
            'full_name'  => (string)$row['full_name'],
            'expires_at' => (string)$row['expires_at'],
        ];
    }

    public function requireRole(array $session, string ...$roles): void
    {
        if (!in_array($session['role'] ?? '', $roles, true)) {
            throw new RuntimeException('forbidden');
        }
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function setCookie(string $sessionId): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_NAME, $sessionId, [
            'expires'  => time() + ($this->lifetimeMinutes * 60),
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $sessionId;
    }

    private function clearCookie(): void
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            setcookie(self::COOKIE_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
            ]);
            unset($_COOKIE[self::COOKIE_NAME]);
        }
    }
}