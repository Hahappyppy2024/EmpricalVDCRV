<?php

declare(strict_types=1);

require_once __DIR__ . '/View.php';

function current_user_id(array $config): ?int
{
    $key = $config['session_user_key'];
    return isset($_SESSION[$key]) ? (int) $_SESSION[$key] : null;
}

function require_login(array $config, bool $api = false): int
{
    $userId = current_user_id($config);
    if ($userId !== null) {
        return $userId;
    }

    if ($api) {
        json_response(['success' => false, 'error' => 'Authentication required'], 401);
    }

    redirect_to('/login');
}

function login_user(array $config, int $userId): void
{
    $_SESSION[$config['session_user_key']] = $userId;
}

function logout_user(array $config): void
{
    unset($_SESSION[$config['session_user_key']]);
}
