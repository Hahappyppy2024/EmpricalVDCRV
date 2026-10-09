<?php
declare(strict_types=1);

namespace LMS\Middleware;

use LMS\Http\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Validates the CSRF token on unsafe HTTP methods.
 *
 * Uses Slim 4 PSR-15 middleware signature.
 */
final class CsrfMiddleware
{
    public function __construct(private Csrf $csrf) {}

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (!$this->csrf->verify($request)) {
                $response = new Response(419);
                $response->getBody()->write(json_encode([
                    'error' => 'CSRF token mismatch.',
                    'status' => 419,
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
        }
        return $handler->handle($request);
    }
}
