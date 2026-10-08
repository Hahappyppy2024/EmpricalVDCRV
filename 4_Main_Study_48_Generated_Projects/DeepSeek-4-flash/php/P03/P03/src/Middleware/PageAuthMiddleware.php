<?php

declare(strict_types=1);

namespace Shop\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Page-level authentication: unauthenticated page requests are redirected to
 * the login page with a deterministic error message.
 */
final class PageAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user === null) {
            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader('Location', '/login' . flash_query('Please sign in to continue.'))
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
