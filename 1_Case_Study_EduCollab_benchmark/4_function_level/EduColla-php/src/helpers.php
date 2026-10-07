<?php

define('APP_BASE', dirname(__DIR__));

function env_bool(string $key, bool $default=false): bool {
    $v = getenv($key);
    if ($v === false) return $default;
    $v = strtolower(trim($v));
    return in_array($v, ['1','true','yes','on'], true);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function json_response($data, int $status=200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_out($data, int $status = 200): void {
    json_response($data, $status);
}


function render(string $view, array $vars = []): void {
    extract($vars);
    $viewFile = APP_BASE . '/views/' . $view . '.php';
    if (!file_exists($viewFile)) {
        http_response_code(500);
        echo "View not found: " . h($view);
        exit;
    }
    $layoutFile = APP_BASE . '/views/layout.php';
    include $layoutFile;
}

function method_override(): string {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($m === 'POST') {
        if (!empty($_POST['_method'])) {
            return strtoupper($_POST['_method']);
        }
        $hdr = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
        if ($hdr) return strtoupper($hdr);
    }
    return strtoupper($m);
}

function request_path(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $qpos = strpos($uri, '?');
    if ($qpos !== false) $uri = substr($uri, 0, $qpos);
    return rtrim($uri, '/') ?: '/';
}

function require_login(): array {
    if (empty($_SESSION['user'])) {
        redirect('/login');
    }
    return $_SESSION['user'];
}

function require_api_login(): array {
    if (empty($_SESSION['user'])) {
        json_response(['error' => 'unauthorized'], 401);
    }
    return $_SESSION['user'];
}

function is_global_admin(?array $user): bool {
    if (!$user) return false;
    // Minimal global admin rule: the seeded admin username is 'admin'
    return ($user['username'] ?? '') === 'admin';
}

function parse_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR); // 抛异常
//    R10:test_error_responses_do_not_leak_internal_details
//    return is_array($data) ? $data : [];
}

function now_iso(): string {
    return gmdate('c');
}

function snippet(string $s, int $max=80): string {
    $s = (string)$s;
    if (strlen($s) <= $max) return $s;
    return substr($s, 0, $max - 3) . '...';
}


function audit_log(PDO $pdo, ?int $userId, string $action, array $meta = []): void {
    $st = $pdo->prepare("INSERT INTO audit_logs(user_id, action, meta_json, created_at) VALUES(?,?,?,?)");
    $st->execute([
        $userId,
        $action,
        json_encode($meta, JSON_UNESCAPED_SLASHES),
        now_iso(),
    ]);
}
