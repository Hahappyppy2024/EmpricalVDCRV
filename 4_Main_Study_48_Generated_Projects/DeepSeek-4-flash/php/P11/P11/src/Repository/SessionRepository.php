<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class SessionRepository extends BaseRepository
{
    public function create(int $userId, string $data = '{}'): array
    {
        $id = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);
        $stmt = $this->db->prepare(
            'INSERT INTO sessions (id, user_id, created_at, expires_at, data) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$id, $userId, $now, $expiresAt, $data]);

        return ['id' => $id, 'user_id' => $userId, 'created_at' => $now, 'expires_at' => $expiresAt, 'data' => $data];
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
        $session = $stmt->fetch();

        if (!$session) {
            return null;
        }
        if (strtotime($session['expires_at']) < time()) {
            $this->delete($id);

            return null;
        }

        return $session;
    }

    public function updateData(string $id, string $data): void
    {
        $this->db->prepare('UPDATE sessions SET data = ? WHERE id = ?')->execute([$data, $id]);
    }

    public function delete(string $id): void
    {
        $this->db->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
    }

    public function deleteExpired(): void
    {
        $this->db->exec("DELETE FROM sessions WHERE expires_at < '" . date('Y-m-d H:i:s') . "'");
    }

    public function logAccess(?int $userId, string $username, string $action, string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO account_access (user_id, username, action, ip, created_at) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$userId, $username, $action, $ip, date('Y-m-d H:i:s')]);
    }

    public function recentAccess(int $limit = 50, ?int $userId = null): array
    {
        if ($userId !== null) {
            $stmt = $this->db->prepare('SELECT * FROM account_access WHERE user_id = ? ORDER BY id DESC LIMIT ?');
            $stmt->execute([$userId, $limit]);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM account_access ORDER BY id DESC LIMIT ?');
            $stmt->execute([$limit]);
        }

        return $stmt->fetchAll();
    }
}
