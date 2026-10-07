<?php
require_once APP_BASE . '/src/repos/UserRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class AuthController {
    public function __construct(private PDO $pdo) {}

    public function showRegister(): void {
        render('auth/register', ['title' => 'Register']);
    }

    public function register(): void {
        require_csrf();
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $display = trim($_POST['display_name'] ?? $username);
        if ($username === '' || $password === '') {
            render('auth/register', ['title' => 'Register', 'error' => 'username and password required']);
            return;
        }
        $repo = new UserRepo($this->pdo);
        try {
            $u = $repo->create($username, $password, $display);
        } catch (Throwable $e) {
            render('auth/register', ['title' => 'Register', 'error' => 'register failed']);
            return;
        }
        session_login_user($u);
        redirect('/courses');
    }

    public function showLogin(): void {
        render('auth/login', ['title' => 'Login']);
    }

    public function login(): void {
        require_csrf();
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $repo = new UserRepo($this->pdo);
        $row = $repo->getAuthByUsername($username);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            render('auth/login', ['title' => 'Login', 'error' => 'invalid credentials']);
            return;
        }
        session_login_user([
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'display_name' => $row['display_name'],
            'created_at' => $row['created_at'],
            'is_global_admin' => (int)($row['is_global_admin'] ?? 0),
        ]);
        redirect('/courses');
    }

    public function logout(): void {
        require_csrf();
        $_SESSION = [];
        if (session_id() !== '' || isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        redirect('/login');
    }

    public function me(): void {
        $u = require_login();
        render('me/me', ['title' => 'Me', 'user' => $u]);
    }

    // API
    public function apiRegister(): void {
        $data = parse_json_body();
        $username = trim($data['username'] ?? '');
        $password = (string)($data['password'] ?? '');
        $display = trim($data['display_name'] ?? $username);
        if ($username === '' || $password === '') json_response(['error' => 'username and password required'], 400);
        $repo = new UserRepo($this->pdo);
        try {
            $u = $repo->create($username, $password, $display);
        } catch (Throwable $e) {
            json_response(['error' => 'register failed'], 400);
        }
        session_login_user($u);
        json_response(['user' => $u]);
    }

    public function apiLogin(): void {
        $data = parse_json_body();
        $username = trim($data['username'] ?? '');
        $password = (string)($data['password'] ?? '');
        $repo = new UserRepo($this->pdo);
        $row = $repo->getAuthByUsername($username);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            json_response(['error' => 'invalid credentials'], 401);
        }
        $u = [
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'display_name' => $row['display_name'],
            'created_at' => $row['created_at'],
            'is_global_admin' => (int)($row['is_global_admin'] ?? 0),
        ];
        session_login_user($u);
        json_response(['user' => $u]);
    }

    public function apiLogout(): void {
        require_api_login();
        $_SESSION = [];
        if (session_id() !== '' || isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        json_response(['ok' => true]);
    }

    public function apiMe(): void {
        $u = require_api_login();
        json_response(['user' => $u]);
    }
}