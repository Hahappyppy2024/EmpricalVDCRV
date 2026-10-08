<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SessionService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $sessionName
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $token = $cookies[$this->sessionName] ?? null;
        $user = $token !== null ? $this->sessions->resolve($token) : null;

        if ($user === null) {
            return $this->deny($request);
        }

        $request = $request
            ->withAttribute('user', $user)
            ->withAttribute('session_token', $token);

        return $handler->handle($request);
    }

    private function deny(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/api/')) {
            $response = $this->responseFactory->createResponse(401)
                ->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => [
                    'code' => 'auth_required',
                    'status' => 401,
                    'message' => 'You must be signed in to continue.',
                    'details' => [],
                ],
            ]));
            return $response;
        }
        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', '/login');
    }
}
