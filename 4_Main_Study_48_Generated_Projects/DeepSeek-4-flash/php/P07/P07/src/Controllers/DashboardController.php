<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Repositories\FileRepository;
use CloudFS\Repositories\UserRepository;
use CloudFS\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class DashboardController
{
    public function __construct(
        private FileRepository $files,
        private UserRepository $users,
        private PhpRenderer $view,
        private AuditService $audit
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        if (!$user) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        $files = $this->files->listForOwner((int) $user['id']);
        $folders = $this->files->listFolders((int) $user['id']);
        $recentAudit = $this->audit->search(['user_id' => (int) $user['id'], 'limit' => 8]);
        return $this->view->render($response, 'dashboard.php', [
            'current_user' => $user,
            'files' => $files,
            'folders' => $folders,
            'recent_audit' => $recentAudit,
        ]);
    }
}
