<?php

declare(strict_types=1);

namespace Shop\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Adds hardening headers to every response (clickjacking, MIME sniffing,
 * referrer leakage).
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin');
    }
}
