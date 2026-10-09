<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => getenv('APP_NAME') ?: 'P01 LMS',
        'env' => getenv('APP_ENV') ?: 'local',
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost:8080',
    ],
    'db' => [
        'path' => getenv('DB_PATH') ?: 'storage/lms.sqlite',
    ],
    'storage' => [
        'uploads' => getenv('UPLOAD_DIR') ?: 'storage/uploads',
        'exports' => getenv('EXPORT_DIR') ?: 'storage/exports',
        'logs'    => 'storage/logs',
    ],
    'session' => [
        'cookie_name' => getenv('SESSION_COOKIE_NAME') ?: 'lms_session',
        'lifetime'    => (int)(getenv('SESSION_LIFETIME_SECONDS') ?: 7200),
    ],
    'ws' => [
        'host' => getenv('WS_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('WS_PORT') ?: 8282),
    ],
    'adapters' => [
        'email'   => getenv('EMAIL_TRANSPORT') ?: 'log',
        'payment' => getenv('PAYMENT_TRANSPORT') ?: 'stub',
        'object_storage' => getenv('OBJECT_STORAGE') ?: 'local',
        'model'   => getenv('MODEL_PROVIDER') ?: 'deterministic',
    ],
];
