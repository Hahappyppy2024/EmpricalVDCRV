<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Central SQLite connection used by every repository, the HTTP application and
 * the Workerman process so that all components share the same data file.
 */
final class Connection
{
    private static ?PDO $pdo = null;

    private static string $dbPath = '';

    public static function configure(string $dbPath): void
    {
        self::$dbPath = $dbPath;
        self::$pdo = null;
    }

    public static function path(): string
    {
        return self::$dbPath;
    }

    public static function db(): PDO
    {
        if (self::$pdo === null) {
            $dir = dirname(self::$dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            self::$pdo = new PDO('sqlite:' . self::$dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }

        return self::$pdo;
    }
}
