<?php
require_once APP_BASE . '/src/repos/UserRepo.php';

class AuthController {
    public function __construct(private PDO $pdo) {}

    // HTML
    public function showRegister(): void {
        render('auth/register', ['title' => 'Register']);
    }

    public function register(): void {
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
        $_SESSION['user'] = $u;
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
            render('auth/login', ['title' => 'Login', 'error' => 'invalid credentials']);
            return;
        }
        $_SESSION['user'] = [
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'display_name' => $row['display_name'],
            'created_at' => $row['created_at'],
        ];
        redirect('/courses');
    }

    public function logout(): void {
        $_SESSION = [];
        if (session_id() !== '' || isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
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
        $_SESSION['user'] = $u;
        json_response(['user' => $u]);
    }


    public function apiLogin(): void {
        $data = parse_json_body();
        $username = trim($data['username'] ?? '');
        $password = (string)($data['password'] ?? '');

        $repo = new UserRepo($this->pdo);
        $row = $repo->getAuthByUsername($username);

        // ❗️Invalid credentials -> return immediately
        if (!$row || !password_verify($password, $row['password_hash'])) {
            json_response(['error' => 'invalid credentials'], 401);
            return;
        }

        $u = [
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'display_name' => $row['display_name'],
            'created_at' => $row['created_at'],
        ];
        $_SESSION['user'] = $u;

        json_response(['user' => $u], 200);
    }
    public function apiLogout(): void {
        require_api_login();
        $_SESSION = [];
        if (session_id() !== '' || isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        session_destroy();
        json_response(['ok' => true]);
    }

    public function apiMe(): void {
        $u = require_api_login();
        json_response(['user' => $u]);
    }
}

