<?php

declare(strict_types=1);

namespace P13\Middleware;

use P13\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Boots the persistent session and exposes the current user to downstream
 * handlers via a request attribute.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private Session $session)
    {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->session->start();
        $request = $request->withAttribute('user', $this->session->user());
        $request = $request->withAttribute('session', $this->session);
        return $handler->handle($request);
    }
}
