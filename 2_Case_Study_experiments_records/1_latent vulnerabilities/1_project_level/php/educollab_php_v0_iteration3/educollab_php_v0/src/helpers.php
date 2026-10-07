<?php

function now_iso(): string {
    return gmdate('c');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render(string $view, array $vars = []): void {
    extract($vars);
    ob_start();
    require APP_BASE . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_BASE . '/views/layout.php';
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function json_response(array $data, int $status = 200): void {
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
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST') {
        if (!empty($_POST['_method'])) {
            $method = strtoupper((string)$_POST['_method']);
        } elseif (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = strtoupper((string)$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
    }
    return $method;
}

function is_json_request(): bool {
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
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
    if (empty($_SESSION['user'])) {
        json_response(['error' => 'unauthorized'], 401);
    }
    return $_SESSION['user'];
}

function is_global_admin(array $user): bool {
    return !empty($user['is_global_admin']);
}

function role_is_staff(string $role): bool {
    return in_array($role, ['teacher', 'admin', 'assistant', 'senior-assistant'], true);
}

function session_login_user(array $user): void {
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['user'] = $user;
}

function session_logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"] ?? '', $params["secure"] ?? false, $params["httponly"] ?? true
        );
    }
    session_destroy();
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
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $sameOrigin = true;

    foreach ([$origin, $referer] as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $candidateHost = (string)(parse_url($candidate, PHP_URL_HOST) ?? '');
        $candidatePort = (string)(parse_url($candidate, PHP_URL_PORT) ?? '');
        $candidateHostPort = $candidateHost;
        if ($candidatePort !== '') {
            $candidateHostPort .= ':' . $candidatePort;
        }
        if ($candidateHostPort !== '' && !hash_equals(strtolower($host), strtolower($candidateHostPort))) {
            $sameOrigin = false;
            break;
        }
    }

    return $sameOrigin;
}

function require_csrf(): void {
    $token = (string)($_POST['_csrf'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    if ($token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token)) {
        return;
    }

    http_response_code(403);
    echo 'CSRF validation failed';
    exit;
}

function snippet(?string $text, int $max = 120): string {
    $text = trim((string)$text);
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max - 1) . '…';
}