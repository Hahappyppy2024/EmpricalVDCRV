<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\UserRepository;
use Shop\Models\PromotionRepository;
use Shop\Models\SettingsRepository;
use Shop\Models\AuditRepository;
use Shop\Models\ReportRepository;

final class SellerAdminOpsController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $this->requireRole($request, 'admin');
        return $this->render($response, 'admin_ops.php', [
            'page_title' => 'Admin operations',
            'users' => UserRepository::list(),
            'promotions' => PromotionRepository::list(),
            'settings' => SettingsRepository::all(),
            'audits' => AuditRepository::recent(20),
        ]);
    }

    public function createPromotion(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'admin');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/admin/operations');
        }
        $code = trim((string)$this->input($request, 'code'));
        $description = trim((string)$this->input($request, 'description'));
        $percent = (int)$this->input($request, 'percent_off');
        if ($code === '' || $percent < 0 || $percent > 100) {
            SessionManager::flash('error', 'Invalid promotion data.');
            return $this->redirect($response, '/admin/operations');
        }
        $id = PromotionRepository::create($code, $description, $percent);
        AuditRepository::log((int)$user['id'], 'promotion_create', 'promotion', (string)$id, ['code' => $code]);
        SessionManager::flash('success', 'Promotion created.');
        return $this->redirect($response, '/admin/operations');
    }

    public function togglePromotion(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'admin');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/admin/operations');
        }
        $id = (int)($args['id'] ?? 0);
        $promo = PromotionRepository::find($id);
        if (!$promo) {
            SessionManager::flash('error', 'Promotion not found.');
            return $this->redirect($response, '/admin/operations');
        }
        PromotionRepository::setActive($id, (int)$promo['active'] === 1 ? false : true);
        AuditRepository::log((int)$user['id'], 'promotion_toggle', 'promotion', (string)$id);
        return $this->redirect($response, '/admin/operations');
    }

    public function setUserStatus(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'admin');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/admin/operations');
        }
        $id = (int)($args['id'] ?? 0);
        $status = (string)$this->input($request, 'status');
        if (!in_array($status, ['active', 'disabled'], true)) {
            SessionManager::flash('error', 'Invalid status.');
            return $this->redirect($response, '/admin/operations');
        }
        if ((int)$user['id'] === $id) {
            SessionManager::flash('error', 'You cannot disable your own account.');
            return $this->redirect($response, '/admin/operations');
        }
        UserRepository::setStatus($id, $status);
        AuditRepository::log((int)$user['id'], 'user_status', 'user', (string)$id, ['status' => $status]);
        SessionManager::flash('success', 'User updated.');
        return $this->redirect($response, '/admin/operations');
    }

    public function updateSetting(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'admin');
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/admin/operations');
        }
        $key = trim((string)$this->input($request, 'key'));
        $value = (string)$this->input($request, 'value');
        if ($key === '') {
            SessionManager::flash('error', 'Key is required.');
            return $this->redirect($response, '/admin/operations');
        }
        SettingsRepository::set($key, $value);
        AuditRepository::log((int)$user['id'], 'setting_update', 'setting', $key, ['value' => $value]);
        SessionManager::flash('success', 'Setting saved.');
        return $this->redirect($response, '/admin/operations');
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $this->requireRole($request, 'admin');
        return $this->json($response, [
            'users' => UserRepository::list(),
            'promotions' => PromotionRepository::list(),
            'settings' => SettingsRepository::all(),
            'audit' => AuditRepository::recent(50),
        ]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'admin');
        $data = $this->jsonBody($request);
        $action = (string)($data['action'] ?? '');
        if ($action === 'promotion') {
            $id = PromotionRepository::create(
                (string)($data['code'] ?? ''),
                (string)($data['description'] ?? ''),
                (int)($data['percent_off'] ?? 0)
            );
            AuditRepository::log((int)$user['id'], 'promotion_create_api', 'promotion', (string)$id);
            return $this->json($response, ['promotion' => PromotionRepository::find($id)], 201);
        }
        return $this->json($response, ['error' => 'invalid_action'], 400);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'admin');
        $data = $this->jsonBody($request);
        $action = (string)($data['action'] ?? '');
        if ($action === 'user_status') {
            $uid = (int)($args['id'] ?? 0);
            $status = (string)($data['status'] ?? '');
            if (!in_array($status, ['active', 'disabled'], true)) {
                return $this->json($response, ['error' => 'invalid_status'], 400);
            }
            UserRepository::setStatus($uid, $status);
            AuditRepository::log((int)$user['id'], 'user_status_api', 'user', (string)$uid, ['status' => $status]);
            return $this->json($response, ['user' => UserRepository::find($uid)]);
        }
        return $this->json($response, ['error' => 'invalid_action'], 400);
    }
}