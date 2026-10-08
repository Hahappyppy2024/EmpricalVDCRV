<?php

declare(strict_types=1);

namespace App;

final class SessionService
{
    private const COOKIE = 'p16_session';

    /** @var array<string, mixed>|null */
    private ?array $currentUser = null;

    public function __construct(
        private readonly Database $db,
        private readonly Config $config
    ) {
    }

    public function cookieName(): string
    {
        return $this->config->get('session.name', self::COOKIE);
    }

    public function lifetimeSeconds(): int
    {
        return (int) $this->config->get('session.lifetime', '7200');
    }

    public function createSession(int $userId, string $ip, string $userAgent): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db->insert('sessions', [
            'user_id' => $userId,
            'token' => hash('sha256', $token),
            'ip_address' => mb_substr($ip, 0, 64),
            'user_agent' => mb_substr($userAgent, 0, 255),
            'expires_at' => date('Y-m-d H:i:s', time() + $this->lifetimeSeconds()),
            'created_at' => $this->db->now(),
        ]);

        return $token;
    }

    /**
     * Resolve the current user from the session cookie.
     *
     * @return array<string, mixed>|null
     */
    public function currentUser(?string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        if ($this->currentUser !== null) {
            return $this->currentUser;
        }
        $row = $this->db->fetchOne(
            'SELECT u.*, s.id AS session_id, s.token AS session_token, s.expires_at AS session_expires
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.token = ? AND s.expires_at > ? AND u.active = 1',
            [hash('sha256', $token), date('Y-m-d H:i:s')]
        );
        if ($row === null) {
            return null;
        }
        $this->currentUser = $row;

        return $row;
    }

    public function destroySession(?string $token): void
    {
        if ($token !== null && $token !== '') {
            $this->db->delete('sessions', ['token' => hash('sha256', $token)]);
        }
        $this->currentUser = null;
    }

    public function purgeExpired(): void
    {
        $this->db->execute("DELETE FROM sessions WHERE expires_at <= datetime('now')");
    }
}
