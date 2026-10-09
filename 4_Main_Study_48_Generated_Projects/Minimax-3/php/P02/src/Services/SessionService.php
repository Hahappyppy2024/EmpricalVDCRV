<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;

final class SessionService
{
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($config['session']['name']);
        session_set_cookie_params([
            'lifetime' => $config['session']['lifetime'],
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::persistToDb();
    }

    private static function persistToDb(): void
    {
        $sid = session_id();
        if (!$sid) return;
        $pdo = Database::pdo();
        $userId = $_SESSION['user_id'] ?? null;
        $expiresAt = date('Y-m-d H:i:s', time() + 7200);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $row = $pdo->prepare('SELECT id FROM sessions WHERE id = ?');
        $row->execute([$sid]);
        if ($row->fetch()) {
            $u = $pdo->prepare('UPDATE sessions SET user_id = ?, expires_at = ?, ip_address = ? WHERE id = ?');
            $u->execute([$userId, $expiresAt, $ip, $sid]);
        } else {
            $i = $pdo->prepare('INSERT INTO sessions (id, user_id, expires_at, ip_address) VALUES (?, ?, ?, ?)');
            $i->execute([$sid, $userId, $expiresAt, $ip]);
        }
    }

    public static function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function user(): ?array
    {
        $uid = self::userId();
        if (!$uid) return null;
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        if (!$u) return null;
        $u['roles'] = explode(',', $u['roles']);
        return $u;
    }

    public static function login(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        self::persistToDb();
    }

    public static function logout(): void
    {
        $sid = session_id();
        if ($sid) {
            $stmt = Database::pdo()->prepare('DELETE FROM sessions WHERE id = ?');
            $stmt->execute([$sid]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function hasRole(array $roles): bool
    {
        $user = self::user();
        if (!$user) return false;
        foreach ($roles as $r) {
            if (in_array($r, $user['roles'], true)) return true;
        }
        return false;
    }

    public static function requireAuth(): void
    {
        if (!self::userId()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireAuth();
        if (!self::hasRole($roles)) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>';
            exit;
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
}