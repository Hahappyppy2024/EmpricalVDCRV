<?php

declare(strict_types=1);

namespace Shop\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Shop\DomainException;
use Shop\SessionManager;

/**
 * CSRF protection for state-changing requests. A token is only enforced when
 * a persistent session exists (anonymous requests such as the login form are
 * allowed through). The token must be supplied via the X-CSRF-Token header or
 * the "_csrf" body field.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): ResponseInterface
    {
        if (!in_array(strtoupper($request->getMethod()), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return $handler->handle($request);
        }

        $token = $_COOKIE[SessionManager::COOKIE] ?? '';
        if ($token === '') {
            return $handler->handle($request);
        }

        $session = $request->getAttribute('session');
        if ($session === null) {
            return $handler->handle($request);
        }

        $header = (string) $request->getHeaderLine('X-CSRF-Token');
        $body = (string) ($request->getParsedBody()['_csrf'] ?? '');
        $expected = (string) $session['csrf_token'];

        if (!hash_equals($expected, $header) && !hash_equals($expected, $body)) {
            throw new DomainException('Invalid or missing CSRF token.', 419);
        }

        return $handler->handle($request);
    }
}
