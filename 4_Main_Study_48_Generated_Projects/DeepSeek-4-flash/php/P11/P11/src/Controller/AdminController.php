<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminService;
use App\Service\QuotaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AdminController extends BaseController
{
    private const ROLES = ['admin'];

    private AdminService $service;

    public function __construct()
    {
        $this->service = new AdminService();
    }

    public function index(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }

        return $this->render($request, $response, 'admin.php', [
            'pageTitle' => 'Admin operations',
            'activeNav' => 'admin',
            'section' => 'overview',
            'settings' => $this->service->settingsAll(),
        ]);
    }

    public function plans(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }

        return $this->render($request, $response, 'admin.php', [
            'pageTitle' => 'Plans',
            'activeNav' => 'admin',
            'section' => 'plans',
            'plans' => $this->service->plans(),
        ]);
    }

    public function storePlan(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $admin = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        [$error, $id] = $this->service->createPlan($admin, $body);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Plan created.');

        return $this->redirect($response, '/admin/plans');
    }

    public function updatePlan(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $admin = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        $error = $this->service->updatePlan($admin, (int) $args['id'], $body);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Plan updated.');

        return $this->redirect($response, '/admin/plans');
    }

    public function deletePlan(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $admin = $this->user($request);
        $error = $this->service->deletePlan($admin, (int) $args['id']);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Plan deleted.');

        return $this->redirect($response, '/admin/plans');
    }

    public function accounts(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }

        return $this->render($request, $response, 'admin.php', [
            'pageTitle' => 'Accounts',
            'activeNav' => 'admin',
            'section' => 'accounts',
            'accounts' => $this->service->accounts(),
            'plans' => $this->service->plans(),
        ]);
    }

    public function storeAccount(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $admin = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        [$error, $id] = $this->service->createAccount($admin, $body);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Account created.');

        return $this->redirect($response, '/admin/accounts');
    }

    public function updateAccount(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $admin = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        $error = $this->service->updateAccount($admin, (int) $args['id'], $body);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Account updated.');

        return $this->redirect($response, '/admin/accounts');
    }

    public function settings(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }

        return $this->render($request, $response, 'admin.php', [
            'pageTitle' => 'Global settings',
            'activeNav' => 'admin',
            'section' => 'settings',
            'settings' => $this->service->settingsAll(),
        ]);
    }

    public function saveSettings(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $denied;
        }
        $admin = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        $error = $this->service->saveSettings($admin, $body);
        $this->flash($request, $error !== null ? 'error' : 'success', $error ?? 'Settings saved.');

        return $this->redirect($response, '/admin/settings');
    }

    public function apiList(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }

        return $this->json($response, [
            'success' => true,
            'plans' => $this->service->plans(),
            'accounts' => $this->service->accounts(),
            'settings' => $this->service->settingsAll(),
        ]);
    }

    public function apiCreate(Request $request, ResponseInterface $response): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $admin = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        if (isset($body['account_username']) || isset($body['username'])) {
            [$error, $id] = $this->service->createAccount($admin, $body);
            if ($error !== null) {
                return $this->error($response, $error, 422);
            }

            return $this->json($response, ['success' => true, 'message' => 'Account created.', 'record_id' => $id], 201);
        }

        [$error, $id] = $this->service->createPlan($admin, $body);
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, ['success' => true, 'message' => 'Plan created.', 'record_id' => $id], 201);
    }

    public function apiUpdate(Request $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $denied = $this->requireRole($request, $response, self::ROLES);
        if ($denied !== null) {
            return $this->error($response, 'Forbidden.', 403);
        }
        $admin = $this->user($request);
        $body = $request->getParsedBody() ?? [];

        if (isset($body['plan_name'])) {
            $error = $this->service->updatePlan($admin, (int) $args['id'], $body);
        } else {
            $error = $this->service->updateAccount($admin, (int) $args['id'], $body);
        }
        if ($error !== null) {
            return $this->error($response, $error, 422);
        }

        return $this->json($response, ['success' => true, 'message' => 'Admin operation applied.']);
    }
}
