<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Restricts a route to one or more roles. Unauthenticated API requests get a
 * stable JSON 401; unauthenticated page requests are redirected to /login.
 */
final class RequireRole implements MiddlewareInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(private array $roles, private ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $user = $request->getAttribute('user');
        $isApi = str_starts_with((string) $request->getUri()->getPath(), '/api/');

        if ($user === null) {
            if ($isApi) {
                return Http::unauthorized($this->responseFactory->createResponse(), 'Authentication required');
            }
            return $this->responseFactory->createResponse(302)->withHeader('Location', '/login');
        }
        if (!in_array($user['role_name'] ?? '', $this->roles, true)) {
            if ($isApi) {
                return Http::deny($this->responseFactory->createResponse());
            }
            return $this->responseFactory->createResponse(302)->withHeader('Location', '/dashboard');
        }
        return $handler->handle($request);
    }
}
