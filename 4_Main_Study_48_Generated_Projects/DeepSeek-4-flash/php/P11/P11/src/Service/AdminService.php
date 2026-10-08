<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\PlanRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Support\Validation;

final class AdminService
{
    private PlanRepository $plans;

    private UserRepository $users;

    private SettingRepository $settings;

    private AuditRepository $audit;

    public function __construct()
    {
        $this->plans = new PlanRepository();
        $this->users = new UserRepository();
        $this->settings = new SettingRepository();
        $this->audit = new AuditRepository();
    }

    public function plans(): array
    {
        return $this->plans->all();
    }

    public function plan(int $id): ?array
    {
        return $this->plans->findById($id);
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, planId]
     */
    public function createPlan(array $admin, array $data): array
    {
        $errors = Validation::required($data, ['name' => 'plan name']);
        if ($errors !== []) {
            return [$errors[0], null];
        }
        if ($this->plans->findByName($data['name']) !== null) {
            return ['A plan with this name already exists.', null];
        }

        $id = $this->plans->create($data);
        $this->audit->record((int) $admin['id'], $admin['username'], 'create', 'admin_operations', 'plan', (string) $id, 'Created plan ' . $data['name']);

        return [null, $id];
    }

    public function updatePlan(array $admin, int $id, array $data): ?string
    {
        $plan = $this->plans->findById($id);
        if ($plan === null) {
            return 'Unknown plan.';
        }
        if (trim((string) ($data['name'] ?? '')) === '') {
            return 'Plan name is required.';
        }
        $this->plans->update($id, $data);
        $this->audit->record((int) $admin['id'], $admin['username'], 'update', 'admin_operations', 'plan', (string) $id, 'Updated plan ' . $data['name']);

        return null;
    }

    public function deletePlan(array $admin, int $id): ?string
    {
        $plan = $this->plans->findById($id);
        if ($plan === null) {
            return 'Unknown plan.';
        }
        $this->plans->delete($id);
        $this->audit->record((int) $admin['id'], $admin['username'], 'delete', 'admin_operations', 'plan', (string) $id, 'Deleted plan ' . $plan['name']);

        return null;
    }

    public function accounts(): array
    {
        return $this->users->all();
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, userId]
     */
    public function createAccount(array $admin, array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = $data['role'] ?? 'customer';
        $planId = isset($data['plan_id']) && $data['plan_id'] !== '' ? (int) $data['plan_id'] : null;

        if ($username === '') {
            return ['Username is required.', null];
        }
        if (!Validation::email($email)) {
            return ['A valid email address is required.', null];
        }
        if (strlen($password) < 8) {
            return ['Password must be at least 8 characters.', null];
        }
        if (!in_array($role, ['customer', 'support', 'admin'], true)) {
            return ['Invalid role.', null];
        }
        if ($this->users->existsUsername($username)) {
            return ['The username is already taken.', null];
        }
        if ($this->users->existsEmail($email)) {
            return ['The email address is already registered.', null];
        }

        $userId = $this->users->create($username, $email, password_hash($password, PASSWORD_DEFAULT), trim((string) ($data['full_name'] ?? '')), $role, $planId);
        $this->audit->record((int) $admin['id'], $admin['username'], 'create', 'admin_operations', 'user', (string) $userId, 'Created account ' . $username);

        return [null, $userId];
    }

    public function updateAccount(array $admin, int $userId, array $data): ?string
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            return 'Unknown account.';
        }
        $fields = [
            'full_name' => trim((string) ($data['full_name'] ?? $user['full_name'])),
            'role' => $data['role'] ?? $user['role'],
        ];
        $planId = isset($data['plan_id']) && $data['plan_id'] !== '' ? (int) $data['plan_id'] : null;
        $fields['plan_id'] = $planId;

        if (!in_array($fields['role'], ['customer', 'support', 'admin'], true)) {
            return 'Invalid role.';
        }
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                return 'Password must be at least 8 characters.';
            }
            $fields['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $this->users->update($userId, $fields);
        $this->audit->record((int) $admin['id'], $admin['username'], 'update', 'admin_operations', 'user', (string) $userId, 'Updated account ' . $user['username']);

        return null;
    }

    public function settingsAll(): array
    {
        $settings = [];
        foreach ($this->settings->all() as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }

    public function saveSettings(array $admin, array $data): ?string
    {
        foreach (['panel_name', 'support_email', 'maintenance_mode', 'default_plan', 'backup_retention_days'] as $key) {
            $this->settings->set($key, trim((string) ($data[$key] ?? '')));
        }
        $this->audit->record((int) $admin['id'], $admin['username'], 'update', 'admin_operations', 'setting', 'global', 'Updated global settings');

        return null;
    }

    public function getSetting(string $key, string $default = ''): string
    {
        return $this->settings->get($key, $default);
    }
}
