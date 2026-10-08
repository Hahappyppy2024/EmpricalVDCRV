<?php

declare(strict_types=1);

namespace P13;

use Dotenv\Dotenv;

/**
 * Environment-based configuration.
 *
 * Values come from the `.env` file when present, otherwise deterministic
 * local defaults documented in `.env.example` are used.
 */
final class Config
{
    private array $config;

    public function __construct(?string $envFile = null)
    {
        $root = dirname(__DIR__);
        $envFile ??= $root . DIRECTORY_SEPARATOR . '.env';

        if (file_exists($envFile)) {
            Dotenv::createImmutable(dirname($envFile))->safeLoad();
        }

        $dbDefault = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'p13.sqlite';

        $this->config = [
            'app.name' => 'P13 Mail Server / Admin Console',
            'app.url' => $this->env('APP_URL', 'http://127.0.0.1:8080'),
            'app.debug' => $this->envBool('APP_DEBUG', false),
            'database.path' => $this->env('DATABASE_PATH', $dbDefault),
            'storage.path' => $this->env('STORAGE_PATH', $root . DIRECTORY_SEPARATOR . 'storage'),
            'session.cookie_name' => $this->env('SESSION_COOKIE_NAME', 'p13_session'),
            'session.ttl_seconds' => (int) $this->env('SESSION_TTL_SECONDS', '86400'),
            'max_upload_mb' => (int) $this->env('MAX_UPLOAD_MB', '10'),
            'ws.url' => $this->env('WS_URL', 'ws://127.0.0.1:8090'),
            'seed.password' => $this->env('SEED_PASSWORD', 'Passw0rd!'),
        ];
    }

    private function env(string $key, string $default): string
    {
        $value = getenv($key);
        return $value === false || $value === '' ? $default : (string) $value;
    }

    private function envBool(string $key, bool $default): bool
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
