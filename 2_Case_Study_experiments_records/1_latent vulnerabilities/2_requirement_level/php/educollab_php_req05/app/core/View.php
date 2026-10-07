<?php

declare(strict_types=1);

function render(string $view, array $params = []): void
{
    extract($params, EXTR_SKIP);
    $viewFile = dirname(__DIR__) . '/views/' . $view . '.php';
    require dirname(__DIR__) . '/views/layouts/header.php';
    require $viewFile;
    require dirname(__DIR__) . '/views/layouts/footer.php';
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input') ?: '';
    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    if ($_POST) {
        return $_POST;
    }

    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}
