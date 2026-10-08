<?php

declare(strict_types=1);

namespace P13;

/**
 * Server-side session service backed by persistent SQLite records and an
 * HTTP-only cookie. Includes a per-session CSRF token.
 */
final class Session
{
    private const GUEST_CSRF_COOKIE = 'p13_csrf';

    private ?array $row = null;
    private ?array $user = null;

    public function __construct(private Database $db, private Config $config)
    {
    }

    public function start(): void
    {
        $name = (string) $this->config->get('session.cookie_name');
        $token = $_COOKIE[$name] ?? null;
        if (!is_string($token) || $token === '') {
            return;
        }
        $row = $this->db->row(
            "SELECT s.id AS sid, s.token AS s_token, s.csrf_token, s.expires_at, s.last_seen_at,
                    u.*
               FROM sessions s
               JOIN users u ON u.id = s.user_id
              WHERE s.token = ? AND s.status = 'active'",
            [$token]
        );
        if ($row === null || strtotime((string) $row['expires_at']) < time()) {
            return;
        }
        $this->row = $row;
        $this->user = $row;
        $this->db->execute(
            "UPDATE sessions SET last_seen_at = datetime('now') WHERE id = ?",
            [$row['sid']]
        );
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : null;
    }

    public function role(): ?string
    {
        return $this->user['role'] ?? null;
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function authenticated(): bool
    {
        return $this->user !== null;
    }

    /**
     * Create a session record and return the cookie token.
     */
    public function create(int $userId, string $ip, string $ua): string
    {
        $token = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(20));
        $ttl = (int) $this->config->get('session.ttl_seconds', 86400);
        $this->db->execute(
            "INSERT INTO sessions (token, csrf_token, user_id, ip_address, user_agent, status, created_at, expires_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, 'active', datetime('now'), datetime('now', ?), datetime('now'))",
            [$token, $csrf, $userId, $ip, $ua, "+{$ttl} seconds"]
        );
        return $token;
    }

    /**
     * Cookie name used for the session token.
     */
    public function cookieName(): string
    {
        return (string) $this->config->get('session.cookie_name');
    }

    /**
     * CSRF token for the current request. For guests it uses a stable
     * per-browser cookie issued on the login/register pages (the caller is
     * responsible for attaching the Set-Cookie header to the response).
     */
    public function csrfToken(): string
    {
        if ($this->authenticated()) {
            return (string) ($this->row['csrf_token'] ?? '');
        }
        $cookie = $_COOKIE[self::GUEST_CSRF_COOKIE] ?? null;
        if (is_string($cookie) && preg_match('/^[a-f0-9]{32,64}$/', $cookie)) {
            return $cookie;
        }
        return bin2hex(random_bytes(20));
    }

    public function guestCsrfCookieName(): string
    {
        return self::GUEST_CSRF_COOKIE;
    }

    public function validateCsrf(string $candidate): bool
    {
        $expected = $this->authenticated()
            ? (string) ($this->row['csrf_token'] ?? '')
            : (string) ($_COOKIE[self::GUEST_CSRF_COOKIE] ?? '');
        return $expected !== '' && hash_equals($expected, $candidate);
    }

    public function destroy(): void
    {
        if ($this->row !== null) {
            $this->db->execute(
                "UPDATE sessions SET status = 'expired', expires_at = datetime('now') WHERE id = ?",
                [$this->row['sid']]
            );
            $this->row = null;
            $this->user = null;
        }
    }
}
