<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Infrastructure\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $cookie = $cookies[Auth::COOKIE] ?? null;
        $user = Auth::fromCookie(is_string($cookie) ? $cookie : null);
        return $handler->handle($request->withAttribute('user', $user));
    }
}