<?php

declare(strict_types=1);

namespace Shop\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shop\Service\AccountService;
use Shop\Service\AdminService;
use Shop\Service\ReviewService;
use Shop\View;

/**
 * Administrator and moderator browser workflows: dashboard, users,
 * platform settings, review moderation and the audit log.
 */
final class AdminController extends Controller
{
    public function __construct(
        private View $view,
        private AdminService $admin,
        private AccountService $accounts,
        private ReviewService $reviews
    ) {
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRole($request, ['admin']);
        return $this->html($response, $this->view->render('admin/dashboard', [
            'stats' => $this->admin->dashboardStats($user),
        ]));
    }

    public function users(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->requireRole($request, ['admin']);
        return $this->html($response, $this->view->render('admin/users', [
            'users' => $this->accounts->listUsers(),
        ]));
    }

    public function userManage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $actor = $this->requireRole($request, ['admin']);
        $data = $this->body($request);
        try {
            $this->admin->manageUser($actor, $this->param($args, 'id'), $data);
            return $this->redirect($response, '/admin/users' . flash_query(null, 'User updated.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/admin/users' . flash_query($e->getMessage()));
        }
    }

    public function settings(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->requireRole($request, ['admin']);
        return $this->html($response, $this->view->render('admin/settings', [
            'settings' => $this->admin->allSettings(),
        ]));
    }

    public function settingsSave(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $actor = $this->requireRole($request, ['admin']);
        $data = $this->body($request);
        try {
            foreach ($data as $key => $value) {
                $this->admin->setSetting($actor, (string) $key, (string) $value);
            }
            return $this->redirect($response, '/admin/settings' . flash_query(null, 'Settings saved.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/admin/settings' . flash_query($e->getMessage()));
        }
    }

    public function reviews(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->requireRole($request, ['admin', 'moderator']);
        return $this->html($response, $this->view->render('admin/reviews', [
            'pending' => $this->reviews->pending(),
            'recent' => $this->reviews->recent(),
        ]));
    }

    public function reviewModerate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $moderator = $this->requireRole($request, ['admin', 'moderator']);
        $data = $this->body($request);
        try {
            $this->reviews->moderate($moderator, $this->param($args, 'id'), (string) ($data['decision'] ?? ''));
            return $this->redirect($response, '/admin/reviews' . flash_query(null, 'Review moderated.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/admin/reviews' . flash_query($e->getMessage()));
        }
    }

    public function audit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->requireRole($request, ['admin']);
        return $this->html($response, $this->view->render('admin/audit', [
            'events' => $this->admin->auditLog(),
        ]));
    }
}
