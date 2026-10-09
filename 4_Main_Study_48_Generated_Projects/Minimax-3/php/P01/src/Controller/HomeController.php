<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Session;
use LMS\Http\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HomeController
{
    public function __construct(
        private View $view,
        private AuthService $auth,
        private Session $session
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->currentUser();
        if ($user) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
        return $this->view->render($response, 'home.php', [
            'flash' => ['notice' => $this->session->takeFlash('notice')],
        ]);
    }
}
