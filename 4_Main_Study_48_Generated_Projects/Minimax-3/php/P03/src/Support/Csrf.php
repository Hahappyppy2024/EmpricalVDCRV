<?php
declare(strict_types=1);

namespace Shop\Support;

final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    public static function check(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $valid = $_SESSION['_csrf'] ?? null;
        if (!is_string($valid) || !is_string($token)) {
            return false;
        }
        if (str_starts_with($token, "\xEF\xBB\xBF")) {
            $token = substr($token, 3);
        }
        if (str_starts_with($valid, "\xEF\xBB\xBF")) {
            $valid = substr($valid, 3);
        }
        return hash_equals($valid, $token);
    }
}