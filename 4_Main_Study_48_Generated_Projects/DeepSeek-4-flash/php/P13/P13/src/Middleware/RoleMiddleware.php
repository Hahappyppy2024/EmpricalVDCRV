<?php

declare(strict_types=1);

namespace P13\Middleware;

use P13\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Requires one of the allowed roles. Non-authorized requests receive a
 * stable 403 error without leaking internals (MAIL-08-FA-03,
 * MAIL-10-FA-03).
 */
final class RoleMiddleware implements MiddlewareInterface
{
    /** @param string[] $roles */
    public function __construct(private Session $session, private array $roles)
    {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $user = $this->session->user();
        $role = $user['role'] ?? null;
        if (is_string($role) && in_array($role, $this->roles, true)) {
            return $handler->handle($request);
        }
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            return error_response(new \Slim\Psr7\Response(), 'Permission denied.', 403);
        }
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write('<html><body><h1>403</h1><p>Permission denied.</p><p><a href="/dashboard">Back to dashboard</a></p></body></html>');
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(403);
    }
}
