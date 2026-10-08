<?php

declare(strict_types=1);

namespace P13\Middleware;

use P13\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF protection for all state-changing requests. Token is supplied via
 * the X-CSRF-Token header or the `_csrf` form field and validated against
 * the persistent session (or the guest CSRF cookie on the auth pages).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private Session $session)
    {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return $handler->handle($request);
        }
        $token = $request->getHeaderLine('X-CSRF-Token');
        if ($token === '') {
            $parsed = $request->getParsedBody();
            if (is_array($parsed)) {
                $token = (string) ($parsed['_csrf'] ?? '');
            }
        }
        if ($token === '' || !$this->session->validateCsrf($token)) {
            return error_response(new \Slim\Psr7\Response(), 'Invalid or missing CSRF token.', 403);
        }
        return $handler->handle($request);
    }
}
