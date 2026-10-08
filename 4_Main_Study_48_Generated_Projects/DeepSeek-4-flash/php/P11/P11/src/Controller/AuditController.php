<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuditLogService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuditController extends BaseController
{
    private const ROLES = ['customer', 'admin'];

    private AuditLogService $service;

    public function __construct()
    {
        $this->service = new AuditLogService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->user($request);
        $query = $request->getQueryParams();
        $module = (string) ($query['module'] ?? '');
        $action = (string) ($query['action'] ?? '');
        $username = (string) ($query['username'] ?? '');
        $limit = isset($query['limit']) ? (int) $query['limit'] : 200;

        [$error, $events] = $this->service->search($user, $module, $action, $username, $limit);

        return $this->render($request, $response, 'audit.php', [
            'pageTitle' => 'Audit logs',
            'activeNav' => 'audit',
            'events' => $events,
            'error' => $error,
            'filters' => ['module' => $module, 'action' => $action, 'username' => $username],
            'actions' => $this->service->allActions(),
            'isAdmin' => $user['role'] === 'admin',
        ]);
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $user = $this->user($request);
        $query = $request->getQueryParams();

        [$error, $events] = $this->service->search(
            $user,
            (string) ($query['module'] ?? ''),
            (string) ($query['action'] ?? ''),
            (string) ($query['username'] ?? ''),
            200
        );
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, ['success' => true, 'records' => $events]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        return $this->error($response, 'Audit logs are read-only.', 422);
    }

    public function apiUpdate(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->error($response, 'Audit logs are read-only.', 422);
    }
}
