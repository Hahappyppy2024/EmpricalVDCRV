<?php
declare(strict_types=1);

namespace MailServer\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionService $session, private array $allowedRoles = [])
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->session->start();
        $userId = $this->session->currentUserId();
        if (!$userId) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) {
                $response = new Response();
                $response->getBody()->write(json_encode(['error' => 'unauthorized']));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }
            $response = new Response();
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $pdo = \MailServer\Database\Database::connection();
        $stmt = $pdo->prepare('SELECT id, username, email, full_name, role, domain_id, status FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active') {
            $this->session->logout();
            $response = new Response();
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        if ($this->allowedRoles && !in_array($user['role'], $this->allowedRoles, true)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) {
                $response = new Response();
                $response->getBody()->write(json_encode(['error' => 'forbidden']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
            $response = new Response();
            $response->getBody()->write('<h1>403 Forbidden</h1><p>Your role does not have access to this page.</p><a href="/dashboard">Back</a>');
            return $response->withStatus(403)->withHeader('Content-Type', 'text/html');
        }

        return $handler->handle($request->withAttribute('user', $user));
    }
}