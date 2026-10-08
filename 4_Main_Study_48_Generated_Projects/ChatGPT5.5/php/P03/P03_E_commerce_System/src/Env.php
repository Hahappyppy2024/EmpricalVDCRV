<?php
declare(strict_types=1);
namespace App;
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key,$value] = array_map('trim', explode('=', $line, 2));
            if (!array_key_exists($key, $_ENV)) $_ENV[$key] = trim($value, "\"'");
        }
    }
}
