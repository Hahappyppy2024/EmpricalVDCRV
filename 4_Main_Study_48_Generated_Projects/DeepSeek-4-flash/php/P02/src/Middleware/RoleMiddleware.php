<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @param array<int, string> $roles
     */
    public function __construct(
        private readonly array $roles,
        private readonly ResponseFactoryInterface $responseFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user === null || !in_array((string) $user['role'], $this->roles, true)) {
            $path = $request->getUri()->getPath();
            if (str_starts_with($path, '/api/')) {
                $response = $this->responseFactory->createResponse(403)
                    ->withHeader('Content-Type', 'application/json');
                $response->getBody()->write(json_encode([
                    'ok' => false,
                    'error' => [
                        'code' => 'permission_error',
                        'status' => 403,
                        'message' => 'You are not allowed to perform this action.',
                        'details' => ['role' => $user['role'] ?? null],
                    ],
                ]));
                return $response;
            }
            return $this->responseFactory->createResponse(302)
                ->withHeader('Location', '/dashboard');
        }
        return $handler->handle($request);
    }
}
