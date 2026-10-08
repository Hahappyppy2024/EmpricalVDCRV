<?php

declare(strict_types=1);

namespace App\Controller;

use App\Middleware\SessionMiddleware;
use App\Service\AccountAccessService;
use App\Service\QuotaService;
use App\Service\ResourceUsageService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DashboardController extends BaseController
{
    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireAuth($request, $response);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        $data = [
            'pageTitle' => 'Dashboard',
            'activeNav' => 'dashboard',
            'usage' => (new QuotaService())->usageWithEntities($user),
            'limits' => (new QuotaService())->limits($user),
            'usageData' => (new ResourceUsageService())->dashboard($user),
        ];

        return $this->render($request, $response, 'dashboard.php', $data);
    }
}
