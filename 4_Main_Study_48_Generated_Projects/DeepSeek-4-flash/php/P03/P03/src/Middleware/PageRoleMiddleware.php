<?php

declare(strict_types=1);

namespace Shop\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Page-level role gate for privileged pages.
 */
final class PageRoleMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(private array $roles)
    {
    }

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user === null) {
            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader('Location', '/login' . flash_query('Please sign in to continue.'))
                ->withStatus(302);
        }
        if (!in_array($user['role'], $this->roles, true)) {
            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader('Location', '/' . flash_query('You are not authorized to access that page.'))
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
