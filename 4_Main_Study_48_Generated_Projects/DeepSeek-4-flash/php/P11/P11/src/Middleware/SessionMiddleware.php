<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves the HTTP-only session cookie into a persistent SQLite session
 * record and attaches both the session and its owning user to the request.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public const COOKIE = 'host_session';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $token = $cookies[self::COOKIE] ?? null;

        $session = null;
        $user = null;
        if (is_string($token) && $token !== '') {
            $session = (new SessionRepository())->find($token);
            if ($session !== null) {
                $user = (new UserRepository())->findById((int) $session['user_id']);
                if ($user === null) {
                    $session = null;
                }
            }
        }

        $request = $request
            ->withAttribute('session', $session)
            ->withAttribute('user', $user);

        return $handler->handle($request);
    }
}
