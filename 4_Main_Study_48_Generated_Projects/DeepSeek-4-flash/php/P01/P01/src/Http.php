<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Small HTTP helpers shared by controllers. JSON payloads always use the
 * stable shape {ok, data|error} so the frontend can render loading,
 * empty, and error states consistently.
 */
final class Http
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    /**
     * @param mixed $data
     */
    public static function ok(Response $response, $data): Response
    {
        return self::json($response, 200, ['ok' => true, 'data' => $data]);
    }

    /**
     * @param mixed $data
     */
    public static function created(Response $response, $data): Response
    {
        return self::json($response, 201, ['ok' => true, 'data' => $data]);
    }

    /**
     * @param array<string, string> $errors
     */
    public static function validation(Response $response, array $errors): Response
    {
        return self::json($response, 422, ['ok' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Validation failed', 'fields' => $errors]]);
    }

    public static function error(Response $response, int $status, string $code, string $message): Response
    {
        return self::json($response, $status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
    }

    public static function deny(Response $response, string $message = 'You are not authorized to perform this action'): Response
    {
        return self::error($response, 403, 'FORBIDDEN', $message);
    }

    public static function unauthorized(Response $response, string $message = 'Authentication required'): Response
    {
        return self::error($response, 401, 'UNAUTHENTICATED', $message);
    }

    public static function notFound(Response $response, string $message = 'Record not found'): Response
    {
        return self::error($response, 404, 'NOT_FOUND', $message);
    }

    /**
     * Render a PHP view inside the shared layout. The view file is captured
     * as $content, then layout.php renders the full HTML document.
     *
     * @param array<string, mixed> $vars
     */
    public static function view(Response $response, Config $config, string $view, array $vars = []): Response
    {
        $viewsDir = dirname(__DIR__) . '/src/Views/';
        $viewFile = $viewsDir . $view . '.php';
        $vars['config'] = $config;
        $vars['view'] = $view;
        $vars['user'] = $vars['user'] ?? null;

        $content = '';
        ob_start();
        try {
            extract($vars, EXTR_SKIP);
            include $viewFile;
            $content = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $layoutVars = [
            'config' => $config,
            'user' => $vars['user'],
            'title' => $vars['title'] ?? ($vars['pageTitle'] ?? 'Dashboard'),
            'content' => $content,
            'active' => $vars['active'] ?? '',
        ];
        $html = '';
        ob_start();
        try {
            extract($layoutVars, EXTR_SKIP);
            include $viewsDir . 'layout.php';
            $html = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Read the JSON request body as an associative array.
     *
     * @return array<string, mixed>
     */
    public static function body(Request $request): array
    {
        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
