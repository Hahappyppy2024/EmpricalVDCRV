<?php

declare(strict_types=1);

/**
 * Global helper functions shared by templates and controllers.
 */

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatBytes')) {
    function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes;
        $unit = 'B';
        foreach ($units as $u) {
            $value /= 1024;
            $unit = $u;
            if ($value < 1024) {
                break;
            }
        }

        return round($value, 1) . ' ' . $unit;
    }
}

if (!function_exists('badge')) {
    function badge(string $status): string
    {
        $map = [
            'active' => 'badge-green',
            'deployed' => 'badge-green',
            'ready' => 'badge-green',
            'issued' => 'badge-green',
            'open' => 'badge-blue',
            'answered' => 'badge-yellow',
            'closed' => 'badge-gray',
            'suspended' => 'badge-red',
            'failed' => 'badge-red',
            'expired' => 'badge-red',
            'revoked' => 'badge-red',
            'requested' => 'badge-yellow',
            'pending' => 'badge-yellow',
            'restoring' => 'badge-yellow',
            'restored' => 'badge-green',
            'idle' => 'badge-gray',
            'running' => 'badge-blue',
            'last_failed' => 'badge-red',
        ];

        return $map[$status] ?? 'badge-gray';
    }
}
