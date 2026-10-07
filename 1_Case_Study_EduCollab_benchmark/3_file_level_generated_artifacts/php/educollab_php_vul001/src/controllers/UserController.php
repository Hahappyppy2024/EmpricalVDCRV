<?php
require_once APP_BASE . '/src/repos/UserRepo.php';

class UserController {
    public function __construct(private PDO $pdo) {}

    public function adminList(): void {
        $u = require_login();
        if (!is_global_admin($u)) {
            http_response_code(403); echo 'Forbidden'; exit;
        }
        $repo = new UserRepo($this->pdo);
        render('admin/users', ['title' => 'Users', 'users' => $repo->listAll()]);
    }

    public function apiList(): void {
        $u = require_api_login();
        if (!is_global_admin($u)) json_response(['error' => 'forbidden'], 403);
        $repo = new UserRepo($this->pdo);
        json_response(['users' => $repo->listAll()]);
    }
}

