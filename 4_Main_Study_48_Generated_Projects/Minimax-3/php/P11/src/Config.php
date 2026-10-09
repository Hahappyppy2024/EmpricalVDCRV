<?php
declare(strict_types=1);

namespace App;

final class Config
{
    public static function load(): void
    {
        $root = dirname(__DIR__);
        $envPath = $root . '/.env';
        $envExample = $root . '/.env.example';
        if (!file_exists($envPath) && file_exists($envExample)) {
            copy($envExample, $envPath);
        }
        $path = file_exists($envPath) ? $envPath : $envExample;
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            $v = trim($v, "\"' \t");
            if (getenv($k) === false || getenv($k) === '') putenv("$k=$v");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v === false || $v === '') return $default;
        return $v;
    }
}