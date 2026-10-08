<?php

declare(strict_types=1);

namespace Shop\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Shop\SessionManager;
use Shop\View;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private \PDO $pdo,
        private View $view
    ) {
    }

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $user = null;
        $session = null;
        $csrf = '';

        $token = $_COOKIE[SessionManager::COOKIE] ?? '';
        if ($token !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT s.id AS session_id, s.token AS session_token, s.csrf_token AS csrf_token, u.*, r.code AS role
                 FROM sessions s
                 JOIN users u ON u.id = s.user_id
                 JOIN roles r ON r.id = u.role_id
                 WHERE s.token = ? AND s.expires_at > datetime(\'now\')'
            );
            $stmt->execute([$token]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $user = $row;
                $session = $row;
                $csrf = (string) $row['csrf_token'];
            }
        }

        $this->view->put('user', $user);
        $this->view->put('csrf', $csrf);

        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('session', $session);
        $request = $request->withAttribute('csrf', $csrf);

        return $handler->handle($request);
    }
}
