<?php
declare(strict_types=1);

namespace Shop\Support;

final class FlashBag
{
    public static function render(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        $error = $_SESSION['_flash']['error'] ?? null;
        $notice = $_SESSION['_flash']['notice'] ?? null;
        $success = $_SESSION['_flash']['success'] ?? null;
        unset($_SESSION['_flash']);
        $html = '';
        foreach (['error' => $error, 'success' => $success, 'notice' => $notice] as $class => $msg) {
            if ($msg) {
                $safe = htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8');
                $html .= "<div class=\"flash flash-{$class}\">{$safe}</div>";
            }
        }
        return $html;
    }
}