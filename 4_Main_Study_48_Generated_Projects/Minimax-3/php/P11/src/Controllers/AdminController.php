<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SettingsRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminController
{
    public function __construct(
        private UserRepository $users,
        private PlanRepository $plans,
        private SettingsRepository $settings,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = $this->users->all();
        $plans = $this->plans->all();
        $settings = $this->settings->all();
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['users' => $users, 'plans' => $plans, 'settings' => $settings]);
        return View::render($response, 'admin', ['user' => $request->getAttribute('user'), 'users' => $users, 'plans' => $plans, 'settings' => $settings, 'flash' => Flash::pull()]);
    }

    public function createUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $actor = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $username = trim((string)($body['username'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        $fullName = trim((string)($body['full_name'] ?? ''));
        $role = trim((string)($body['role'] ?? 'customer'));
        $planId = isset($body['plan_id']) && $body['plan_id'] !== '' ? (int)$body['plan_id'] : null;
        $password = (string)($body['password'] ?? 'Password123!');

        if (!Validator::username($username) || !Validator::email($email)) {
            Flash::set('error', 'Invalid username or email'); return View::redirect($response, '/admin?error=validation');
        }
        if (!Validator::oneOf($role, ['customer','support','admin'])) {
            Flash::set('error', 'Invalid role'); return View::redirect($response, '/admin?error=role');
        }
        if ($this->users->findByUsernameOrEmail($username) || $this->users->findByUsernameOrEmail($email)) {
            Flash::set('error', 'User already exists'); return View::redirect($response, '/admin?error=duplicate');
        }
        $id = $this->users->create($username, $email, password_hash($password, PASSWORD_BCRYPT), $fullName, $role, $planId);
        AuditLogger::log((int)$actor['id'], $actor['role'], 'admin.user.create', 'user', $id, $username, $this->ip($request));
        Flash::set('success', "User $username created");
        return View::redirect($response, '/admin');
    }

    public function updateUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $actor = $request->getAttribute('user');
        $id = (int)$args['id'];
        $body = (array)$request->getParsedBody();
        if (isset($body['role'])) {
            $role = trim((string)$body['role']);
            if (!Validator::oneOf($role, ['customer','support','admin'])) return View::json($response, ['error' => 'invalid_role'], 422);
            $this->users->setRole($id, $role);
            AuditLogger::log((int)$actor['id'], $actor['role'], 'admin.user.role', 'user', $id, $role, $this->ip($request));
        }
        if (isset($body['plan_id'])) {
            $planId = $body['plan_id'] === '' ? null : (int)$body['plan_id'];
            $this->users->setPlan($id, $planId);
            AuditLogger::log((int)$actor['id'], $actor['role'], 'admin.user.plan', 'user', $id, (string)$planId, $this->ip($request));
        }
        if (array_key_exists('is_active', $body)) {
            $this->users->setActive($id, ((string)$body['is_active']) === '1' || $body['is_active'] === 'true');
            AuditLogger::log((int)$actor['id'], $actor['role'], 'admin.user.active', 'user', $id, (string)$body['is_active'], $this->ip($request));
        }
        return View::json($response, ['status' => 'ok']);
    }

    public function updateSetting(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $actor = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $key = trim((string)($body['key'] ?? ''));
        $value = (string)($body['value'] ?? '');
        if (!Validator::nonEmpty($key)) return View::redirect($response, '/admin?error=key');
        $this->settings->set($key, $value);
        AuditLogger::log((int)$actor['id'], $actor['role'], 'admin.settings.update', 'settings', null, "$key=$value", $this->ip($request));
        Flash::set('success', "Setting $key updated");
        return View::redirect($response, '/admin');
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}