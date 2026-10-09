<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Infrastructure\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $user = $request->getAttribute('user');
            $userId = is_array($user) && isset($user['user_id']) ? (int)$user['user_id'] : null;
            $params = (array)$request->getParsedBody();
            $token = $params['_csrf'] ?? $request->getHeaderLine('X-CSRF-Token');
            if (!$token || !Csrf::validate($userId, is_string($token) ? $token : null)) {
                $response = new Response();
                $response->getBody()->write(json_encode(['error' => 'invalid_csrf_token']));
                return $response->withStatus(419)->withHeader('Content-Type', 'application/json');
            }
        }
        return $handler->handle($request);
    }
}