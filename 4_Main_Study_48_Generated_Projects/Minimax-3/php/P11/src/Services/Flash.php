<?php
declare(strict_types=1);

namespace App\Services;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (session_status() === PHP_SESSION_NONE) @session_start();
        }
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pull(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }
}