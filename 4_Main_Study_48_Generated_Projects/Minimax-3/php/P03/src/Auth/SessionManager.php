<?php
declare(strict_types=1);

namespace Shop\Auth;

use Shop\Config;
use Shop\Database;

final class SessionManager
{
    public const FLASH_KEY = '_flash';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $name = Config::get('SESSION_NAME', 'SHOP_SESSION') ?: 'SHOP_SESSION';
        session_name($name);
        $params = [
            'lifetime' => (int)(Config::get('SESSION_LIFETIME', '86400')),
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($params);
        session_start();

        $sid = session_id();
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + $params['lifetime']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 250) : null;
        $userId = $_SESSION['user_id'] ?? null;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO sessions (id, user_id, payload, ip_address, user_agent, created_at, last_seen_at, expires_at)
             VALUES (:id, :uid, :payload, :ip, :ua, :now, :now, :exp)
             ON CONFLICT(id) DO UPDATE SET
                user_id = excluded.user_id,
                last_seen_at = excluded.last_seen_at,
                expires_at = excluded.expires_at'
        );
        $stmt->execute([
            ':id' => $sid,
            ':uid' => $userId,
            ':payload' => '{}',
            ':ip' => $ip,
            ':ua' => $ua,
            ':now' => $now,
            ':exp' => $expires,
        ]);

        // Persist final payload at request shutdown so we capture mutations
        // made by Csrf::token() and other session-mutating helpers.
        register_shutdown_function(static function () use ($sid, $ip, $ua, $expires) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return;
            }
            $payload = json_encode($_SESSION, JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                $payload = '{}';
            }
            $userId = $_SESSION['user_id'] ?? null;
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'UPDATE sessions SET payload = :p, user_id = :u, ip_address = :ip, user_agent = :ua, last_seen_at = datetime("now"), expires_at = :exp WHERE id = :id'
            );
            $stmt->execute([
                ':p' => $payload,
                ':u' => $userId,
                ':ip' => $ip,
                ':ua' => $ua,
                ':exp' => $expires,
                ':id' => $sid,
            ]);
        });
    }

    public static function userId(): ?int
    {
        self::start();
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : ($id !== null ? (int)$id : null);
    }

    public static function user(): ?array
    {
        $uid = self::userId();
        if ($uid === null) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, email, display_name, role, status FROM users WHERE id = :id');
        $stmt->execute([':id' => $uid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function login(int $userId, string $role): void
    {
        self::start();
        $oldId = session_id();
        session_regenerate_id(true);
        $newId = session_id();
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $role;
        $_SESSION['login_at'] = date('c');

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM sessions WHERE id = :id')->execute([':id' => $oldId]);
        $payload = json_encode($_SESSION, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            $payload = '{}';
        }
        $expires = date('Y-m-d H:i:s', time() + (int)(Config::get('SESSION_LIFETIME', '86400')));
        $stmt = $pdo->prepare(
            'INSERT INTO sessions (id, user_id, payload, ip_address, user_agent, created_at, last_seen_at, expires_at)
             VALUES (:id, :uid, :payload, :ip, :ua, :now, :now, :exp)
             ON CONFLICT(id) DO UPDATE SET
                user_id = excluded.user_id,
                payload = excluded.payload,
                last_seen_at = excluded.last_seen_at,
                expires_at = excluded.expires_at'
        );
        $stmt->execute([
            ':id' => $newId,
            ':uid' => $userId,
            ':payload' => $payload,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 250) : null,
            ':now' => date('Y-m-d H:i:s'),
            ':exp' => $expires,
        ]);
    }

    public static function logout(): void
    {
        self::start();
        $sid = session_id();
        if ($sid !== '') {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE id = :id');
            $stmt->execute([':id' => $sid]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }
        session_destroy();
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();
        if (!isset($_SESSION[self::FLASH_KEY]) || !is_array($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }
        if (func_num_args() === 1) {
            $value = $_SESSION[self::FLASH_KEY][$key] ?? null;
            unset($_SESSION[self::FLASH_KEY][$key]);
            return $value;
        }
        $_SESSION[self::FLASH_KEY][$key] = $value;
        return $value;
    }

    public static function has(string $role): bool
    {
        $u = self::user();
        if ($u === null) {
            return false;
        }
        $allowed = self::allowedRoles($role);
        return in_array($u['role'], $allowed, true);
    }

    public static function requireAuth(): void
    {
        if (self::userId() === null) {
            self::flash('error', 'Please sign in to continue.');
            header('Location: /login');
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        if (!self::has($role)) {
            self::flash('error', 'You do not have permission for that action.');
            header('Location: /');
            exit;
        }
    }

    /** @return array<int,string> */
    public static function allowedRoles(string $role): array
    {
        return match ($role) {
            'customer' => ['customer', 'seller', 'admin'],
            'moderator' => ['moderator', 'admin'],
            'seller' => ['seller', 'admin'],
            'admin' => ['admin'],
            'any' => ['customer', 'seller', 'moderator', 'admin'],
            default => [],
        };
    }
}