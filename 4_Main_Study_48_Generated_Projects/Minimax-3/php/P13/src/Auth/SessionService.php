<?php
declare(strict_types=1);

namespace MailServer\Auth;

use MailServer\Database\Database;

class SessionService
{
    private const COOKIE_NAME = 'MSAC_SID';
    private const TTL_SECONDS = 86400;

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name(self::COOKIE_NAME);
        session_start();
    }

    public function login(string $userId, string $ip, string $ua): string
    {
        $sid = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$sid, $userId, $ip, $ua, json_encode(['user_id' => (int) $userId]), $expires]);
        $_SESSION['sid'] = $sid;
        $_SESSION['user_id'] = (int) $userId;
        return $sid;
    }

    public function logout(): void
    {
        $sid = $_SESSION['sid'] ?? null;
        if ($sid) {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE id = ?');
            $stmt->execute([$sid]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function currentUserId(): ?int
    {
        if (!isset($_SESSION['sid'])) {
            return null;
        }
        $sid = $_SESSION['sid'];
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT user_id, expires_at FROM sessions WHERE id = ?');
        $stmt->execute([$sid]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (strtotime($row['expires_at']) < time()) {
            $this->logout();
            return null;
        }
        $stmt = $pdo->prepare('UPDATE sessions SET last_seen_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$sid]);
        return (int) $row['user_id'];
    }

    public function recordAccess(int $userId, string $type, string $ip, string $ua, bool $success, string $detail = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO account_access (user_id, access_type, remote_ip, user_agent, success, detail) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $type, $ip, $ua, $success ? 1 : 0, $detail]);
    }

    public function recordAudit(int $actorUserId, string $actorRole, string $action, string $targetType = null, string $targetId = null, string $detail = null, string $ip = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO audit_events (actor_user_id, actor_role, action, target_type, target_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorUserId, $actorRole, $action, $targetType, $targetId, $detail, $ip]);
    }
}