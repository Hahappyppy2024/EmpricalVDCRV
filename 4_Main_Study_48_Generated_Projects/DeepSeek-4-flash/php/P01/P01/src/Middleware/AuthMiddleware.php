<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Resolves the signed-in user from the HTTP-only session cookie and attaches
 * the user (or null) to the request as the "user" attribute.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $token = AuthService::tokenFromRequest($request);
        $user = $token !== null ? $this->auth->userFromToken($token) : null;
        return $handler->handle($request->withAttribute('user', $user));
    }
}
