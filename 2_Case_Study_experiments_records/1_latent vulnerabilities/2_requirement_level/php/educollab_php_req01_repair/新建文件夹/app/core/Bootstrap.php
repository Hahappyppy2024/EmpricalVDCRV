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
            created_at TEXT NOT NULL
        )'
    );

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

function seed_admin(array $config): void
{
    $userRepo = new UserRepository(Database::connection($config));
    $admin = $userRepo->findByUsername($config['admin']['username']);
    if ($admin) {
        return;
    }

    $userRepo->create([
        'username' => $config['admin']['username'],
        'password_hash' => password_hash($config['admin']['password'], PASSWORD_DEFAULT),
        'display_name' => $config['admin']['display_name'],
    ]);
}
