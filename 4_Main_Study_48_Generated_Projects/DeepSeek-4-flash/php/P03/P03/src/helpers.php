<?php

declare(strict_types=1);

/**
 * HTML-escape a value for safe output in templates.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format an amount as currency.
 */
function money(float $amount, string $currency = 'USD'): string
{
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥'];
    return ($symbols[$currency] ?? $currency . ' ') . number_format($amount, 2);
}

/**
 * Build a ?error=&ok= query string for deterministic page feedback.
 */
function flash_query(?string $error = null, ?string $ok = null): string
{
    $parts = [];
    if ($error !== null) {
        $parts[] = 'error=' . rawurlencode($error);
    }
    if ($ok !== null) {
        $parts[] = 'ok=' . rawurlencode($ok);
    }
    return $parts ? ('?' . implode('&', $parts)) : '';
}

/**
 * Hidden CSRF input for server-rendered forms.
 */
function csrf_field(?string $token): string
{
    return '<input type="hidden" name="_csrf" value="' . e((string) ($token ?? '')) . '">';
}

/**
 * Simple JSON payload writer used by the WebSocket server.
 */
function ws_json(string $event, array $payload): string
{
    return json_encode(['event' => $event, 'payload' => $payload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
