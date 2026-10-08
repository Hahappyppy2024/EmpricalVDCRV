<?php

declare(strict_types=1);

namespace Shop;

final class Config
{
    private static bool $loaded = false;

    public static function load(string $dir): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable($dir)->safeLoad();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }

    public static function int(string $key, int $default): int
    {
        return (int) self::get($key, $default);
    }

    public static function projectDir(): string
    {
        return dirname(__DIR__);
    }

    public static function absolute(string $path): string
    {
        if ($path !== '' && $path[0] === '/' && str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return $path;
        }
        return self::projectDir() . DIRECTORY_SEPARATOR . $path;
    }
}
