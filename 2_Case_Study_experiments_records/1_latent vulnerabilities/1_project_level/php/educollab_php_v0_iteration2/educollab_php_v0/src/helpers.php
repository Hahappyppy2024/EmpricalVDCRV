<?php

function now_iso(): string {
    return gmdate('c');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render(string $view, array $vars = []): void {
    extract($vars);
    $title = $vars['title'] ?? 'EduCollab';
    ob_start();
    require APP_BASE . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_BASE . '/views/layout.php';
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function parse_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function request_path(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return parse_url($uri, PHP_URL_PATH) ?: '/';
}

function method_override(): string {
    $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($m === 'POST' && isset($_POST['_method'])) {
        $o = strtoupper((string)$_POST['_method']);
        if (in_array($o, ['PUT','PATCH','DELETE'], true)) return $o;
    }
    return $m;
}

function require_login(): array {
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }
    return $_SESSION['user'];
}

function require_api_login(): array {
    if (empty($_SESSION['user'])) json_response(['error' => 'unauthorized'], 401);
    return $_SESSION['user'];
}

function is_global_admin(array $user): bool {
    return !empty($user['is_global_admin']);
}

function session_login_user(array $user): void {
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['user'] = $user;
}

function session_logout_user(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function role_is_staff(string $role): bool {
    return in_array($role, ['teacher', 'ta'], true);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function same_origin_request(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return true;
    }

    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?? ''));
        return $originHost === '' || $originHost === $host;
    }

    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        $refererHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?? ''));
        return $refererHost === '' || $refererHost === $host;
    }

    return true;
}

function require_csrf(): void {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $token = (string)($_POST['_csrf'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    if ($token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token)) {
        return;
    }

    if (!same_origin_request()) {
        http_response_code(403);
        echo 'CSRF validation failed';
        exit;
    }
}