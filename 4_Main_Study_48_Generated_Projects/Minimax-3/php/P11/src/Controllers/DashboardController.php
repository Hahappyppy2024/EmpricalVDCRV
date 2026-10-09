<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DomainRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TicketRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\BackupRepository;
use App\Services\Flash;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DashboardController
{
    public function __construct(
        private DomainRepository $domains,
        private SiteRepository $sites,
        private TicketRepository $tickets,
        private ResourceRepository $resources,
        private BackupRepository $backups,
    ) {}

    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $counts = [
            'domains' => count($this->domains->listForUser((int)$user['id'])),
            'sites'   => count($this->sites->listForUser((int)$user['id'])),
            'tickets' => count($this->tickets->listForUser((int)$user['id'], $user['role'])),
            'backups' => count($this->backups->listForUser((int)$user['id'])),
        ];
        $resource = $this->resources->summary((int)$user['id']);
        return View::render($response, 'dashboard', [
            'user' => $user, 'counts' => $counts, 'resource' => $resource, 'flash' => Flash::pull(),
        ]);
    }
}