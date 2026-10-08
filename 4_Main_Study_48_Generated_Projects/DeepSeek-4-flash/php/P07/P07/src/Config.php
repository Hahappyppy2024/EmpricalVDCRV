<?php

declare(strict_types=1);

namespace CloudFS;

use Dotenv\Dotenv;

final class Config
{
    public static function load(string $rootDir): array
    {
        if (file_exists($rootDir . '/.env')) {
            Dotenv::createImmutable($rootDir)->safeLoad();
        }
        $dbPath = self::env('DB_PATH', 'storage/db/cloudfs.sqlite');
        if (str_starts_with($dbPath, '/') === false && !preg_match('/^[A-Za-z]:[\\\\\/]/', $dbPath)) {
            $dbPath = $rootDir . '/' . $dbPath;
        }
        $storagePath = self::env('STORAGE_PATH', 'storage/files');
        if (str_starts_with($storagePath, '/') === false && !preg_match('/^[A-Za-z]:[\\\\\/]/', $storagePath)) {
            $storagePath = $rootDir . '/' . $storagePath;
        }
        return [
            'root_dir' => $rootDir,
            'app_name' => self::env('APP_NAME', 'Cloud File-Sharing System'),
            'app_env' => self::env('APP_ENV', 'development'),
            'app_debug' => self::env('APP_DEBUG', 'false') === 'true',
            'app_host' => self::env('APP_HOST', '127.0.0.1'),
            'app_port' => (int) self::env('APP_PORT', '8080'),
            'db_path' => $dbPath,
            'storage_path' => $storagePath,
            'session_cookie' => self::env('SESSION_COOKIE_NAME', 'cfss_session'),
            'session_lifetime' => (int) self::env('SESSION_LIFETIME_SECONDS', '7200'),
            'ws_host' => self::env('WS_HOST', '127.0.0.1'),
            'ws_port' => (int) self::env('WS_PORT', '8282'),
        ];
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);
        return $value === false || $value === '' ? $default : (string) $value;
    }
}
