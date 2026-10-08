<?php

declare(strict_types=1);

namespace CloudFS\Middleware;

use CloudFS\Services\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionService $sessions)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $token = $cookies[$this->sessions->cookieName()] ?? null;
        $user = null;
        if ($token) {
            $user = $this->sessions->resolve($token);
        }
        return $handler->handle($request->withAttribute('current_user', $user));
    }
}
