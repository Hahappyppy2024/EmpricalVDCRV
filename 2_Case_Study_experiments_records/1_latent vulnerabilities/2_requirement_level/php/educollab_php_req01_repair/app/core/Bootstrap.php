<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__) . '/repositories/UserRepository.php';
require_once dirname(__DIR__) . '/repositories/CourseRepository.php';

function init_db(array $config): void
{
    $pdo = Database::connection($config);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            display_name TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )'
    );

    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    $hasIsAdmin = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? null) === 'is_admin') {
            $hasIsAdmin = true;
            break;
        }
    }
    if (!$hasIsAdmin) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS courses (
            course_id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            created_by INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(created_by) REFERENCES users(user_id)
        )'
    );
}

function seed_admin(array $config): bool
{
    $username = trim((string) ($config['admin']['username'] ?? ''));
    $password = (string) ($config['admin']['password'] ?? '');
    $displayName = trim((string) ($config['admin']['display_name'] ?? 'Bootstrap Admin'));

    if ($username === '' || $password === '') {
        return false;
    }

    $userRepo = new UserRepository(Database::connection($config));
    $admin = $userRepo->findByUsername($username);
    if ($admin) {
        return $userRepo->setAdminStatus((int) $admin['user_id'], true);
    }

    $userRepo->create([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => $displayName !== '' ? $displayName : 'Bootstrap Admin',
        'is_admin' => 1,
    ]);

    return true;
}