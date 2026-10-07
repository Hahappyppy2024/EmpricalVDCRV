<?php

define('APP_BASE', dirname(__DIR__));

function env_bool(string $key, bool $default=false): bool {
    $v = getenv($key);
    if ($v === false) return $default;
    $v = strtolower(trim($v));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function now_iso(): string {
    return gmdate('c');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response($data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function request_path(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    return $path ?: '/';
}

function method_override(): string {
    $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($m === 'POST' && isset($_POST['_method'])) {
        $override = strtoupper(trim((string)$_POST['_method']));
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) return $override;
    }
    return $m;
}

function parse_json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function is_json_request(): bool {
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $ctype = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    return str_contains($accept, 'application/json') || str_contains($ctype, 'application/json');
}

function render(string $view, array $params=[]): void {
    extract($params);
    $viewFile = APP_BASE . '/views/' . $view . '.php';
    $title = $params['title'] ?? 'EduCollab';
    include APP_BASE . '/views/layout.php';
}

function role_is_staff(string $role): bool {
    return in_array($role, ['teacher', 'ta'], true);
}

function is_global_admin(array $user): bool {
    return !empty($user['is_global_admin']);
}

function require_login(): array {
    $u = $_SESSION['user'] ?? null;
    if (!$u) {
        redirect('/login');
    }
    return $u;
}

function require_api_login(): array {
    $u = $_SESSION['user'] ?? null;
    if (!$u) json_response(['error' => 'authentication required'], 401);
    return $u;
}

function session_login_user(array $user): void {
    session_regenerate_id(true);
    unset($_SESSION['_csrf_token']);
    $_SESSION['user'] = $user;
}

function session_logout_user(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['_csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void {
    $token = (string)($_POST['_csrf'] ?? '');
    $expected = (string)($_SESSION['_csrf_token'] ?? '');
    if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
        if (is_json_request()) {
            json_response(['error' => 'invalid csrf token'], 403);
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function snippet(?string $text, int $max = 120): string {
    $text = trim((string)$text);
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max - 1) . '…';
}