<?php
declare(strict_types=1);

namespace LMS\Bootstrap;

use PDO;

/**
 * Thin singleton-style accessor for the SQLite connection.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static ?string $path = null;

    public static function init(string $path): void
    {
        self::$path = $path;
        self::$pdo = null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        if (self::$path === null) {
            throw new \RuntimeException('Database not initialised');
        }
        if (!str_starts_with(self::$path, DIRECTORY_SEPARATOR) && !preg_match('#^[A-Za-z]:[\\\\/]#', self::$path)) {
            self::$path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::$path;
        }
        if (!is_file(self::$path)) {
            throw new \RuntimeException('Database file not found: ' . self::$path);
        }
        $pdo = new PDO('sqlite:' . self::$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function path(): string
    {
        return self::$path ?? '';
    }
}
