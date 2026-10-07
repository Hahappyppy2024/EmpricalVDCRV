<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class AuthController
{
    public function __construct(
        private UserRepository $userRepo,
        private array $config
    ) {
    }

    public function showRegister(): void
    {
        render('auth/register', ['title' => 'Register', 'error' => null]);
    }

    public function registerHtml(): void
    {
        $data = request_data();
        $result = $this->register($data);
        if (!$result['success']) {
            render('auth/register', ['title' => 'Register', 'error' => $result['error']]);
            return;
        }

        login_user($this->config, $result['user']['user_id']);
        redirect_to('/me');
    }

    public function registerApi(): void
    {
        $result = $this->register(request_data());
        if (!$result['success']) {
            json_response($result, 422);
        }
        login_user($this->config, $result['user']['user_id']);
        json_response($result, 201);
    }

    private function register(array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $displayName = trim((string) ($data['display_name'] ?? ''));

        if ($username === '' || $password === '' || $displayName === '') {
            return ['success' => false, 'error' => 'username, password, and display_name are required'];
        }

        if ($this->userRepo->findByUsername($username)) {
            return ['success' => false, 'error' => 'Username already exists'];
        }

        $userId = $this->userRepo->create([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
        ]);

        $user = $this->userRepo->findById($userId);
        return ['success' => true, 'user' => $user];
    }

    public function showLogin(): void
    {
        render('auth/login', ['title' => 'Login', 'error' => null]);
    }

    public function loginHtml(): void
    {
        $result = $this->login(request_data());
        if (!$result['success']) {
            render('auth/login', ['title' => 'Login', 'error' => $result['error']]);
            return;
        }

        login_user($this->config, $result['user']['user_id']);
        redirect_to('/me');
    }

    public function loginApi(): void
    {
        $result = $this->login(request_data());
        if (!$result['success']) {
            json_response($result, 401);
        }

        login_user($this->config, $result['user']['user_id']);
        json_response($result, 200);
    }

    private function login(array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return ['success' => false, 'error' => 'username and password are required'];
        }

        $user = $this->userRepo->findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        return ['success' => true, 'user' => $this->userRepo->findById((int) $user['user_id'])];
    }

    public function logoutHtml(): void
    {
        logout_user($this->config);
        redirect_to('/login');
    }

    public function logoutApi(): void
    {
        logout_user($this->config);
        json_response(['success' => true]);
    }

    public function meHtml(): void
    {
        $userId = require_login($this->config, false);
        $user = $this->userRepo->findById($userId);
        render('auth/me', ['title' => 'My Account', 'user' => $user]);
    }

    public function meApi(): void
    {
        $userId = require_login($this->config, true);
        $user = $this->userRepo->findById($userId);
        json_response(['success' => true, 'user' => $user]);
    }

    public function listUsersHtml(): void
    {
        require_login($this->config, false);
        render('admin/users', ['title' => 'Users', 'users' => $this->userRepo->all()]);
    }

    public function listUsersApi(): void
    {
        require_login($this->config, true);
        json_response(['success' => true, 'users' => $this->userRepo->all()]);
    }
}
