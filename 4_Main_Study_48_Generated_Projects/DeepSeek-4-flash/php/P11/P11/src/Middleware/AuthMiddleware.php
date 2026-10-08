<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Http;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Attaches the signed-in user to the request context (set by SessionMiddleware).
 * Public routes remain reachable; protected controllers enforce their own guards.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute('user', Http::user($request));

        return $handler->handle($request);
    }
}
