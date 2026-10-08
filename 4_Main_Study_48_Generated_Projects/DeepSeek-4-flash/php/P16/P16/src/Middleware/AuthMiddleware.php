<?php

declare(strict_types=1);

namespace App\Middleware;

use App\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly SessionService $sessions)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getCookieParams()[$this->sessions->cookieName()] ?? '';
        $user = $this->sessions->currentUser($token);
        if ($user === null) {
            $this->sessions->destroySession($token !== '' ? $token : null);
            if (str_starts_with($request->getUri()->getPath(), '/api/')) {
                $response = $this->jsonResponse(401, ['ok' => false, 'error' => 'Authentication required.', 'code' => 'UNAUTHENTICATED']);

                return $response;
            }
            $response = new \Slim\Psr7\Response();

            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $user['ip'] = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        return $handler->handle($request->withAttribute('user', $user));
    }

    public static function jsonResponse(int $status, array $payload): ResponseInterface
    {
        $response = new \Slim\Psr7\Response($status);
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
