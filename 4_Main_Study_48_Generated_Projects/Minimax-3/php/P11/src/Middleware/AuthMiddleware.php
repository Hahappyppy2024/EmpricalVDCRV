<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Auth\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class BootstrapMiddleware
{
    public static function publicPaths(): array
    {
        return [
            '/login', '/register', '/assets/',
        ];
    }

    public static function isPublic(string $path): bool
    {
        foreach (self::publicPaths() as $p) {
            if ($p === $path) return true;
            if (str_ends_with($p, '/') && str_starts_with($path, $p)) return true;
        }
        return false;
    }
}

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionService $sessions) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Allow public paths through.
        if (BootstrapMiddleware::isPublic($path) || $path === '/') {
            return $handler->handle($request);
        }

        $user = $this->sessions->current();
        if (!$user) {
            if (str_starts_with($path, '/api/')) {
                $resp = new Response(401);
                $resp->getBody()->write(json_encode(['error' => 'unauthenticated']));
                return $resp->withHeader('Content-Type', 'application/json');
            }
            $resp = new Response(302);
            return $resp->withHeader('Location', '/login');
        }
        return $handler->handle($request->withAttribute('user', $user));
    }
}