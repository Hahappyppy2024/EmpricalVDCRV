<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Http\Csrf;
use LMS\Http\View;
use LMS\Auth\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Error responses (LMS-14) — intentionally triggers stable, generic errors.
 */
final class ErrorController
{
    public function __construct(
        private View $view,
        private Csrf $csrf
    ) {}

    public function demo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $kind = (string)($args['kind'] ?? '');
        return match ($kind) {
            'validation' => $this->view->render($response->withStatus(422), 'error.php', [
                'error' => 'Validation failed: a required field is missing or invalid.',
                'status' => 422,
            ]),
            'auth' => $this->view->render($response->withStatus(401), 'error.php', [
                'error' => 'Authentication required.',
                'status' => 401,
            ]),
            'forbidden' => $this->view->render($response->withStatus(403), 'error.php', [
                'error' => 'You are not allowed to perform this action.',
                'status' => 403,
            ]),
            'notfound' => $this->view->render($response->withStatus(404), 'error.php', [
                'error' => 'The requested resource was not found.',
                'status' => 404,
            ]),
            'csrf' => $this->view->render($response->withStatus(419), 'error.php', [
                'error' => 'Session token expired. Please reload the page and try again.',
                'status' => 419,
            ]),
            default => $this->view->render($response->withStatus(500), 'error.php', [
                'error' => 'An unexpected error occurred. Please try again later.',
                'status' => 500,
            ]),
        };
    }
}
