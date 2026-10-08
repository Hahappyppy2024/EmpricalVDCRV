<?php

declare(strict_types=1);

namespace CloudFS\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class RequireAdminMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('current_user');
        if (!$user) {
            return (new RequireAuthMiddleware())->process($request, $handler);
        }
        if (($user['role'] ?? 'user') !== 'admin') {
            $response = new Response(403);
            $response->getBody()->write(json_encode(['ok' => false, 'error' => 'Admin privileges are required for this action.']));
            return $response->withHeader('Content-Type', 'application/json');
        }
        return $handler->handle($request);
    }
}
