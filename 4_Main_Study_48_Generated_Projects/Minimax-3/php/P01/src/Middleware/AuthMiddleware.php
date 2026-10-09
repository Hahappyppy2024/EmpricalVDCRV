<?php
declare(strict_types=1);

namespace LMS\Middleware;

use LMS\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Routes that require authentication (PSR-15).
 */
final class AuthMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler, string ...$roles): ResponseInterface
    {
        $user = $this->auth->currentUser();
        if (!$user) {
            if ($this->expectsJson($request)) {
                return $this->jsonError(401, 'Authentication required.');
            }
            $response = new Response();
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        if (!empty($roles) && !in_array($user['role'], $roles, true)) {
            if ($this->expectsJson($request)) {
                return $this->jsonError(403, 'Insufficient privileges.');
            }
            $response = new Response();
            $response->getBody()->write('Forbidden');
            return $response->withStatus(403);
        }
        return $handler->handle($request->withAttribute('user', $user));
    }

    private function expectsJson(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        return str_contains($accept, 'application/json') || str_starts_with($request->getUri()->getPath(), '/api/');
    }

    private function jsonError(int $status, string $message): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(json_encode(['error' => $message, 'status' => $status]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
