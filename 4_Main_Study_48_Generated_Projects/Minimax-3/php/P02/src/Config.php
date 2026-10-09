<?php
declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;

final class Config
{
    public static function load(string $rootPath): array
    {
        if (file_exists($rootPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->safeLoad();
        }

        return [
            'app' => [
                'env' => $_ENV['APP_ENV'] ?? 'local',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
                'name' => $_ENV['CONFERENCE_NAME'] ?? 'Synthetic Conference',
            ],
            'db' => [
                'path' => $_ENV['DB_PATH'] ?? './storage/database.sqlite',
            ],
            'paths' => [
                'uploads' => $_ENV['UPLOAD_DIR'] ?? './storage/uploads',
                'exports' => $_ENV['EXPORT_DIR'] ?? './storage/exports',
                'storage' => $rootPath . '/storage',
            ],
            'session' => [
                'name' => $_ENV['SESSION_NAME'] ?? 'conf_session',
                'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
            ],
        ];
    }
}
