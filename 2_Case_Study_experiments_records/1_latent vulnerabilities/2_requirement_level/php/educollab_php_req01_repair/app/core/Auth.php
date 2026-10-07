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
    session_regenerate_id(true);
    $_SESSION[$config['session_user_key']] = $userId;
}

function logout_user(array $config): void
{
    unset($_SESSION[$config['session_user_key']]);
    session_regenerate_id(true);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(bool $api = false): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    $knownToken = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : '';

    if ($token !== '' && $knownToken !== '' && hash_equals($knownToken, $token)) {
        return;
    }

    if ($api) {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function require_admin(array $config, UserRepository $userRepo, bool $api = false): int
{
    $userId = require_login($config, $api);
    $user = $userRepo->findById($userId);
    if ($user && !empty($user['is_admin'])) {
        return $userId;
    }

    if ($api) {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function is_admin_user(UserRepository $userRepo, int $userId): bool
{
    $user = $userRepo->findById($userId);
    return $user ? !empty($user['is_admin']) : false;
}