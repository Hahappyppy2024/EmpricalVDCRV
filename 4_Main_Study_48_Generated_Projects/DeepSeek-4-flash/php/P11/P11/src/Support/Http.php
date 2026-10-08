<?php

declare(strict_types=1);

namespace App\Support;

use App\Repository\SessionRepository;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response as SlimResponse;

final class Http
{
    /**
     * Read the authenticated user attached by the Auth middleware.
     */
    public static function user(RequestInterface $request): ?array
    {
        return $request->getAttribute('user');
    }

    public static function session(RequestInterface $request): ?array
    {
        return $request->getAttribute('session');
    }

    public static function isAuth(RequestInterface $request): bool
    {
        return self::user($request) !== null;
    }

    public static function hasRole(RequestInterface $request, array $roles): bool
    {
        $user = self::user($request);
        if ($user === null) {
            return false;
        }

        return in_array($user['role'], $roles, true);
    }

    public static function clientIp(RequestInterface $request): string
    {
        $server = $request->getServerParams();

        return (string) ($server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public static function setFlash(RequestInterface $request, string $key, string $value): void
    {
        $session = self::session($request);
        if ($session === null) {
            return;
        }
        $data = json_decode($session['data'], true);
        if (!is_array($data)) {
            $data = [];
        }
        $data['flash'][$key] = $value;
        (new SessionRepository())->updateData($session['id'], json_encode($data));
    }

    public static function flash(RequestInterface $request, string $key): ?string
    {
        $session = self::session($request);
        if ($session === null) {
            return null;
        }
        $data = json_decode($session['data'], true);
        if (!is_array($data) || !isset($data['flash'][$key])) {
            return null;
        }
        $value = $data['flash'][$key];
        unset($data['flash'][$key]);
        (new SessionRepository())->updateData($session['id'], json_encode($data));

        return $value;
    }

    public static function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        return $response->withHeader('Location', $url)->withStatus(302);
    }

    public static function error(ResponseInterface $response, string $message, int $status = 400): ResponseInterface
    {
        return self::json($response, ['success' => false, 'error' => $message, 'message' => $message], $status);
    }

    public static function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $request = $data['request'] ?? null;
        $user = self::user($request);
        $data['user'] = $user;

        $session = self::session($request);
        $flash = null;
        if ($session !== null) {
            $sess = json_decode($session['data'], true);
            $flash = is_array($sess) ? ($sess['flash'] ?? []) : [];
        }
        $data['flash'] = $flash;

        $body = '';
        ob_start();
        extract($data, EXTR_SKIP);
        include __DIR__ . '/../views/' . $template;
        $body = ob_get_clean();

        $data['content'] = $body;
        ob_start();
        extract($data, EXTR_SKIP);
        include __DIR__ . '/../views/layout.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function denyIfNotAuthenticated(RequestInterface $request, ResponseInterface $response): ?ResponseInterface
    {
        if (!self::isAuth($request)) {
            return self::redirect($response, '/login');
        }

        return null;
    }

    public static function denyIfRoleNotAllowed(RequestInterface $request, ResponseInterface $response, array $roles): ?ResponseInterface
    {
        if (!self::isAuth($request)) {
            return self::redirect($response, '/login');
        }
        if (!self::hasRole($request, $roles)) {
            $body = $response->getBody();
            $body->write('<!DOCTYPE html><html><body><h1>403 Forbidden</h1><p>You do not have permission to access this page.</p><p><a href="/">Back to dashboard</a></p></body></html>');

            return $response->withStatus(403)->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        return null;
    }
}
