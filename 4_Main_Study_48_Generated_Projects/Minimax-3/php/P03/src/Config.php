<?php
declare(strict_types=1);

namespace Shop;

final class Config
{
    private static array $values = [];

    public static function load(): void
    {
        $root = dirname(__DIR__);
        $envPath = $root . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(ltrim($line), '#')) {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                $v = trim($v, "\"'");
                self::$values[$k] = $v;
            }
        }
        self::$values['APP_ROOT'] = $root;
        if (!isset(self::$values['APP_ENV'])) {
            self::$values['APP_ENV'] = 'local';
        }
        if (!isset(self::$values['APP_DEBUG'])) {
            self::$values['APP_DEBUG'] = 'true';
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }
}