<?php

declare(strict_types=1);

namespace App;

/**
 * Loads configuration from the environment file (.env) with deterministic
 * defaults. Missing values fall back to the defaults defined here so the
 * application runs offline after dependencies are installed.
 */
final class Config
{
    /** @var array<string, string> */
    private array $values = [];

    public function __construct(private string $rootDir)
    {
        $this->values = $this->loadEnvFile();
    }

    /**
     * Parse a standard KEY=VALUE dot-env file. Lines starting with '#' or
     * empty lines are ignored. Values are trimmed of surrounding quotes.
     *
     * @return array<string, string>
     */
    private function loadEnvFile(): array
    {
        $values = [];
        $file = $this->rootDir . DIRECTORY_SEPARATOR . '.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, "\"'");
                if ($key !== '') {
                    $values[$key] = $value;
                }
            }
        }
        return $values;
    }

    public function rootDir(): string
    {
        return $this->rootDir;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }

    public function env(): string
    {
        return $this->get('APP_ENV', 'local');
    }

    public function baseUrl(): string
    {
        return rtrim($this->get('BASE_URL', 'http://localhost:8080'), '/');
    }

    public function dbPath(): string
    {
        $path = $this->get('DB_PATH', 'data/lms.sqlite');
        if (!$this->isAbsolute($path)) {
            $path = $this->rootDir . DIRECTORY_SEPARATOR . $path;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $path;
    }

    public function uploadDir(): string
    {
        $path = $this->get('UPLOAD_DIR', 'data/uploads');
        if (!$this->isAbsolute($path)) {
            $path = $this->rootDir . DIRECTORY_SEPARATOR . $path;
        }
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return $path;
    }

    public function exportDir(): string
    {
        $path = $this->get('EXPORT_DIR', 'data/exports');
        if (!$this->isAbsolute($path)) {
            $path = $this->rootDir . DIRECTORY_SEPARATOR . $path;
        }
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return $path;
    }

    public function sessionLifetimeMinutes(): int
    {
        return max(1, (int) $this->get('SESSION_LIFETIME_MINUTES', '480'));
    }

    public function allowRegistration(): bool
    {
        return strtolower($this->get('ALLOW_REGISTRATION', 'true')) === 'true';
    }

    public function wsHost(): string
    {
        return $this->get('WS_HOST', '127.0.0.1');
    }

    public function wsPort(): int
    {
        return max(1, (int) $this->get('WS_PORT', '8280'));
    }

    public function siteName(): string
    {
        return $this->get('SITE_NAME', 'P01 Learning Management System');
    }

    public function isProduction(): bool
    {
        return strtolower($this->env()) === 'production';
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('#^[A-Za-z]:[/\\\\]#', $path) === 1;
    }
}
