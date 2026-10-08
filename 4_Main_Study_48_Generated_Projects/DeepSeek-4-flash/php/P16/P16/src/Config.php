<?php

declare(strict_types=1);

namespace App;

final class Config
{
    /** @var array<string, string> */
    private array $values;

    public function __construct(array $env)
    {
        $root = dirname(__DIR__);
        $this->values = [
            'app.url' => $env['APP_URL'] ?? 'http://127.0.0.1:8080',
            'app.env' => $env['APP_ENV'] ?? 'development',
            'app.secret' => $env['APP_SECRET'] ?? 'p16-local-dev-secret-change-me',
            'db.path' => $env['DB_PATH'] ?? $root . '/storage/monitoring.sqlite',
            'session.name' => $env['SESSION_NAME'] ?? 'p16_session',
            'session.lifetime' => $env['SESSION_LIFETIME'] ?? '7200',
            'ws.url' => $env['WS_URL'] ?? 'ws://127.0.0.1:9090',
            'ws.enabled' => $env['WS_ENABLED'] ?? '0',
            'ws.port' => $env['WS_PORT'] ?? '9090',
            'backup.dir' => $env['BACKUP_DIR'] ?? $root . '/storage/backups',
            'upload.dir' => $env['UPLOAD_DIR'] ?? $root . '/storage/uploads',
            'log.dir' => $env['LOG_DIR'] ?? $root . '/storage/logs',
        ];
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->values[$key] ?? $default);
    }
}
