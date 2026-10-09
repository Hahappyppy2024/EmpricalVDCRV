<?php
declare(strict_types=1);

namespace App\Auth;

use App\Database;
use App\Middleware\CsrfMiddleware;

final class SessionService
{
    public function start(): void
    {
        // Use CsrfMiddleware's session bootstrap so cookie name & params are identical.
        CsrfMiddleware::ensureSession();
    }

    public function login(int $userId, string $ip, string $userAgent): string
    {
        $this->start();
        // Rotate the PHP session id and store our opaque session id alongside.
        session_regenerate_id(true);
        $sid = bin2hex(random_bytes(24));
        $_SESSION['sid'] = $sid;
        $_SESSION['uid'] = $userId;

        $expires = date('Y-m-d H:i:s', time() + (int)(getenv('SESSION_LIFETIME') ?: 86400));
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sessions (id, user_id, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$sid, $userId, $ip, substr($userAgent, 0, 255), $expires]);
        return $sid;
    }

    public function logout(): void
    {
        $this->start();
        $sid = $_SESSION['sid'] ?? null;
        if ($sid) {
            $stmt = Database::pdo()->prepare('DELETE FROM sessions WHERE id = ?');
            $stmt->execute([$sid]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public function current(): ?array
    {
        $this->start();
        $sid = $_SESSION['sid'] ?? null;
        $uid = $_SESSION['uid'] ?? null;
        if (!$sid || !$uid) return null;

        $stmt = Database::pdo()->prepare(
            'SELECT s.id AS sid, s.expires_at, u.id, u.username, u.email, u.full_name, u.role, u.plan_id
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE s.id = ? AND s.user_id = ? AND s.expires_at > datetime("now") AND u.is_active = 1'
        );
        $stmt->execute([$sid, $uid]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->logout();
            return null;
        }
        return $row;
    }
}