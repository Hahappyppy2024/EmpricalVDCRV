<?php

declare(strict_types=1);

namespace P13\Middleware;

use P13\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Requires an authenticated session. API calls receive a stable 401 JSON
 * envelope; browser requests are redirected to the login page.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Session $session)
    {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($this->session->authenticated()) {
            return $handler->handle($request);
        }
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            $response = new \Slim\Psr7\Response();
            return error_response($response, 'Unauthenticated.', 401);
        }
        return redirect_response(new \Slim\Psr7\Response(), '/login', 302);
    }
}
