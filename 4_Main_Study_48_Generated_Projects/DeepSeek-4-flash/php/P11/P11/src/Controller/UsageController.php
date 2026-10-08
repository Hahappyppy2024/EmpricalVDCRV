<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ResourceUsageService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UsageController extends BaseController
{
    private const ROLES = ['customer', 'support', 'admin'];

    private ResourceUsageService $service;

    public function __construct()
    {
        $this->service = new ResourceUsageService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);

        return $this->render($request, $response, 'usage.php', [
            'pageTitle' => 'Resource usage',
            'activeNav' => 'usage',
            'dashboard' => $this->service->usageForCustomer($user),
            'overview' => in_array($user['role'], ['support', 'admin'], true) ? $this->service->overview() : [],
        ]);
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);

        return $this->json($response, [
            'success' => true,
            'records' => $this->service->usageForCustomer($user)['history'],
        ]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $body = $request->getParsedBody() ?? [];
        $cpu = (float) ($body['cpu_usage'] ?? 0);
        $disk = (int) ($body['disk_used'] ?? 0);
        $traffic = (int) ($body['traffic_used'] ?? 0);

        $id = $this->service->record((int) $user['id'], $cpu, $disk, $traffic, 0.0);

        return $this->json($response, [
            'success' => true,
            'message' => 'Usage report recorded.',
            'record_id' => $id,
        ], 201);
    }

    public function apiUpdate(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->error($response, 'Usage records are read-only; use POST to append a report.', 422);
    }
}
