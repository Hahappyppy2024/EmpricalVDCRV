<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        // Windows absolute path
        $isWindowsAbsolute =
            preg_match('/^[A-Za-z]:[\\\\\/]/', $path);

        $isUnixAbsolute =
            str_starts_with($path, '/');

        if (!$isWindowsAbsolute && !$isUnixAbsolute) {
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $path;
        }

        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdo = new PDO(
            'sqlite:' . $path,
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }
}