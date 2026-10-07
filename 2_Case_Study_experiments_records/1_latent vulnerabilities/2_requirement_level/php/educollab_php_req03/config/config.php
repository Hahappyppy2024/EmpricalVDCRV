<?php

declare(strict_types=1);

return [
    'db_path' => dirname(__DIR__) . '/data/app.db',
    'session_user_key' => 'current_user_id',
    'admin' => [
        'username' => 'admin',
        'password' => 'admin123',
        'display_name' => 'Default Admin',
    ],
];
