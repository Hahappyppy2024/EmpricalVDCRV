<?php

declare(strict_types=1);

namespace CloudFS\Services;

use CloudFS\Database\Database;

final class SessionService
{
    public function __construct(
        private Database $db,
        private string $cookieName,
        private int $lifetimeSeconds
    ) {
    }

    public function create(int $userId, ?string $ip, ?string $userAgent): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $this->lifetimeSeconds);
        $id = $this->db->insert(
            'INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, $token, $ip, $userAgent, $expiresAt]
        );
        return ['id' => $id, 'token' => $token, 'expires_at' => $expiresAt];
    }

    public function resolve(string $token): ?array
    {
        $session = $this->db->one(
            'SELECT u.id, u.username, u.email, u.full_name, u.role, u.quota_bytes,
                    s.id AS session_id, s.token, s.expires_at, s.ip_address, s.user_agent
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.token = ? AND s.expires_at > datetime(\'now\')',
            [$token]
        );
        if (!$session) {
            return null;
        }
        $this->db->run('UPDATE sessions SET last_activity = datetime(\'now\') WHERE id = ?', [(int) $session['session_id']]);
        return $session;
    }

    public function destroy(string $token): void
    {
        $this->db->run('DELETE FROM sessions WHERE token = ?', [$token]);
    }

    public function destroyAllForUser(int $userId, string $exceptToken): int
    {
        return (int) $this->db->run(
            'DELETE FROM sessions WHERE user_id = ? AND token <> ?',
            [$userId, $exceptToken]
        )->rowCount();
    }

    public function listForUser(int $userId): array
    {
        return $this->db->all(
            'SELECT id, ip_address, user_agent, created_at, expires_at, last_activity FROM sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50',
            [$userId]
        );
    }

    public function cookieName(): string
    {
        return $this->cookieName;
    }
}
