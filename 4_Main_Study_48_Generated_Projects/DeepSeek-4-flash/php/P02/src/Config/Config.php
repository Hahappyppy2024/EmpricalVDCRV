<?php

declare(strict_types=1);

namespace App\Config;

final class Config
{
    /**
     * @param string $rootDir Absolute path of the project root.
     * @return array<string, mixed>
     */
    public static function load(string $rootDir): array
    {
        $dotenv = \Dotenv\Dotenv::createImmutable($rootDir);
        $dotenv->safeLoad();

        $root = rtrim($rootDir, '/\\');

        return [
            'root' => $root,
            'app_name' => self::env('APP_NAME', 'Conference Review System'),
            'env' => self::env('APP_ENV', 'local'),
            'app_url' => self::env('APP_URL', 'http://localhost:8080'),
            'db_path' => $root . DIRECTORY_SEPARATOR . self::env('DB_PATH', 'database/conference.sqlite'),
            'session_name' => self::env('SESSION_NAME', 'conf_session'),
            'session_lifetime' => (int) self::env('SESSION_LIFETIME', '86400'),
            'storage_path' => $root . DIRECTORY_SEPARATOR . self::env('STORAGE_PATH', 'storage'),
            'ws_port' => (int) self::env('WS_PORT', '8090'),
            'ws_url' => self::env('WS_URL', 'ws://127.0.0.1:8090'),
            'mail_log' => $root . DIRECTORY_SEPARATOR . self::env('MAIL_TO_FILE', 'storage/mail.log'),
            'error_log' => $root . DIRECTORY_SEPARATOR . self::env('ERROR_LOG', 'storage/errors.log'),
            'app_key' => self::env('APP_KEY', 'local-dev-key'),
            'display_errors' => self::env('APP_ENV', 'local') === 'local',
        ];
    }

    private static function env(string $key, string $default): string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }
        $value = getenv($key);
        return $value === false ? $default : (string) $value;
    }
}
