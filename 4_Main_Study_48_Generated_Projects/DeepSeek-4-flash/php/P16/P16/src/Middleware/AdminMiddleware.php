<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user') ?? [];
        if ((string) ($user['role'] ?? '') !== 'admin') {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) {
                return AuthMiddleware::jsonResponse(403, [
                    'ok' => false,
                    'error' => 'Administrator role required.',
                    'code' => 'FORBIDDEN',
                ]);
            }
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write('403 Forbidden - Administrator role required.');

            return $response->withStatus(403);
        }

        return $handler->handle($request);
    }
}
