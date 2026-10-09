<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CsrfMiddleware implements MiddlewareInterface
{
    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $name = getenv('SESSION_NAME') ?: 'panel_session';
        // Always configure session parameters BEFORE the first start
        if (session_status() === PHP_SESSION_NONE) {
            session_name($name);
            session_set_cookie_params([
                'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 86400),
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => false,
            ]);
        }
        @session_start();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        self::ensureSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !str_starts_with($request->getUri()->getPath(), '/api/')) {
            $token = $request->getParsedBody()['_csrf'] ?? $request->getHeaderLine('X-CSRF-Token');
            if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
                $resp = new \Slim\Psr7\Response(419);
                $resp->getBody()->write('CSRF token mismatch');
                return $resp;
            }
        }
        return $handler->handle($request);
    }
}