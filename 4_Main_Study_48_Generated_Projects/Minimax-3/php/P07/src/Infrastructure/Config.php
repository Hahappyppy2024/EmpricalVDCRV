<?php
declare(strict_types=1);
namespace App\Infrastructure;

final class Config
{
    private static array $values = [];

    public static function load(): void
    {
        $envFile = __DIR__ . '/../../.env';
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                $key = trim($key);
                $value = trim($value);
                if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                    $value = trim($value, "\"'");
                }
                self::$values[$key] = $value;
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
        foreach (self::$values as $k => $v) {
            if (getenv($k) === false) {
                putenv($k . '=' . $v);
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }
        return $v;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int)$v;
    }
}
Config::load();