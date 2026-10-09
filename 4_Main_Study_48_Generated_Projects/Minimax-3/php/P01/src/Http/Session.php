<?php
declare(strict_types=1);

namespace LMS\Http;

use PDO;

/**
 * Server-side session identified by an HTTP-only cookie.
 *
 * Session records are persisted in the `sessions` table for visibility
 * and audit. The browser only sees an opaque token.
 */
final class Session
{
    private array $config;
    private ?array $data = null;
    private ?string $id = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cookieName = $config['session']['cookie_name'];
        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $config['session']['lifetime'],
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name($cookieName);
        if (PHP_SAPI !== 'cli') {
            @session_start();
        }
    }

    public function start(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    public function id(): string
    {
        if ($this->id !== null) {
            return $this->id;
        }
        $this->start();
        $this->id = session_id() ?: '';
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function flush(): void
    {
        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                $this->config['session']['cookie_name'],
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        @session_destroy();
        $this->id = null;
    }

    public function setFlash(string $key, string $message): void
    {
        $this->start();
        $_SESSION['_flash'][$key] = $message;
    }

    public function takeFlash(string $key): ?string
    {
        $this->start();
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }

    public function csrfToken(): string
    {
        $this->start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public function regenerate(): void
    {
        $this->start();
        @session_regenerate_id(true);
        $this->id = session_id() ?: null;
    }
}
