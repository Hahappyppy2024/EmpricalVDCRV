<?php

declare(strict_types=1);

namespace CloudFS\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('current_user');
        if (!$user) {
            return $this->deny('Authentication required. Sign in to continue.');
        }
        return $handler->handle($request);
    }

    protected function deny(string $message): ResponseInterface
    {
        $response = new Response(401);
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'json')) {
            $response->getBody()->write(json_encode(['ok' => false, 'error' => $message]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write(
            '<!doctype html><meta charset="utf-8"><title>401 Access denied</title>' .
            '<p style="font-family:system-ui">' . htmlspecialchars($message) . '</p>' .
            '<p><a href="/login">Sign in</a></p>'
        );
        return $response;
    }
}
