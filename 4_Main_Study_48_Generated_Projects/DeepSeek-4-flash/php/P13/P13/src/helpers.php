<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Application root directory (one level above this src folder).
 */
function base_path(string $path = ''): string
{
    $root = dirname(__DIR__);
    if ($path === '' || $path === '/') {
        return $root;
    }
    return $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

function storage_path(string $path = ''): string
{
    $storage = base_path('storage');
    if ($path === '') {
        return $storage;
    }
    return $storage . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

/**
 * HTML-escape a value for safe output in templates.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Return a JSON response.
 */
function json_response(Response $response, array $data, int $status = 200): Response
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $response->getBody()->write($payload === false ? '{}' : $payload);
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus($status);
}

/**
 * Return a stable, deterministic error envelope (no stack traces leaked).
 */
function error_response(Response $response, string $message, int $status = 400, array $extra = []): Response
{
    return json_response($response, array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

/**
 * Stable deterministic "ok" envelope.
 */
function ok_response(Response $response, array $data = [], int $status = 200): Response
{
    return json_response($response, array_merge(['ok' => true], $data), $status);
}

/**
 * Redirect response.
 */
function redirect_response(Response $response, string $url, int $status = 302): Response
{
    return $response->withHeader('Location', $url)->withStatus($status);
}

/**
 * Parse a request body as JSON when present, otherwise return empty array.
 */
function json_body(Request $request): array
{
    $body = (string) $request->getBody();
    if ($body === '') {
        return [];
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Client IP with a deterministic fallback.
 */
function client_ip(Request $request): string
{
    $server = $request->getServerParams();
    $ip = $server['REMOTE_ADDR'] ?? '127.0.0.1';
    $fwd = $server['HTTP_X_FORWARDED_FOR'] ?? null;
    if ($fwd !== null && preg_match('/\d{1,3}(?:\.\d{1,3}){3}/', (string) $fwd, $m)) {
        return $m[0];
    }
    return (string) $ip;
}

function client_ua(Request $request): string
{
    return substr((string) $request->getHeaderLine('User-Agent'), 0, 255) ?: 'unknown';
}

/**
 * Deterministic "now" timestamp in SQLite datetime format.
 */
function now_ts(): string
{
    return gmdate('Y-m-d H:i:s');
}

function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}
