<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private array $allowedRoles) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $role = $user['role'] ?? '';
        if (!in_array($role, $this->allowedRoles, true)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) {
                $resp = new Response(403);
                $resp->getBody()->write(json_encode(['error' => 'forbidden', 'required_roles' => $this->allowedRoles]));
                return $resp->withHeader('Content-Type', 'application/json');
            }
            $resp = new Response(302);
            return $resp->withHeader('Location', '/dashboard?error=forbidden');
        }
        return $handler->handle($request);
    }
}