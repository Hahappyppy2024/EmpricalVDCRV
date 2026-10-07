<?php
require_once APP_BASE . '/src/repos/UserRepo.php';

class AuthController {
    public function __construct(private PDO $pdo) {}

    private function sessionUserFromRow(array $row): array {
        return [
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'display_name' => $row['display_name'],
            'is_global_admin' => !empty($row['is_global_admin']),
        ];
    }

    public function showRegister(): void {
        render('auth/register', ['title' => 'Register']);
    }

    public function register(): void {
        $username = trim($_POST['username'] ?? '');
        $display = trim($_POST['display_name'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $display === '' || $password === '') {
            http_response_code(422);
            echo 'Missing required fields';
            return;
        }

        $repo = new UserRepo($this->pdo);
        try {
            $u = $repo->create($username, $password, $display);
        } catch (Throwable $e) {
            http_response_code(409);
            echo 'Username already exists';
            return;
        }

        session_login_user($u);
        redirect('/courses');
    }

    public function showLogin(): void {
        render('auth/login', ['title' => 'Login']);
    }

    public function login(): void {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $repo = new UserRepo($this->pdo);
        $row = $repo->getAuthByUsername($username);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            http_response_code(401);
            echo 'Invalid credentials';
            return;
        }

        session_login_user($this->sessionUserFromRow($row));
        redirect('/courses');
    }

    public function logout(): void {
        session_logout_user();
        redirect('/login');
    }

    public function me(): void {
        $u = require_login();
        render('me/profile', ['title' => 'My Profile', 'user' => $u]);
    }

    public function apiRegister(): void {
        $data = parse_json_body();
        $username = trim((string)($data['username'] ?? ''));
        $display = trim((string)($data['display_name'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($username === '' || $display === '' || $password === '') {
            json_response(['error' => 'missing required fields'], 422);
        }

        $repo = new UserRepo($this->pdo);
        try {
            $u = $repo->create($username, $password, $display);
        } catch (Throwable $e) {
            json_response(['error' => 'username already exists'], 409);
        }

        session_login_user($u);
        json_response(['user' => $u], 201);
    }

    public function apiLogin(): void {
        $data = parse_json_body();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $repo = new UserRepo($this->pdo);
        $row = $repo->getAuthByUsername($username);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            json_response(['error' => 'invalid credentials'], 401);
        }

        $u = $this->sessionUserFromRow($row);
        session_login_user($u);
        json_response(['user' => $u]);
    }

    public function apiLogout(): void {
        session_logout_user();
        json_response(['ok' => true]);
    }

    public function apiMe(): void {
        $u = require_api_login();
        json_response(['user' => $u]);
    }
}