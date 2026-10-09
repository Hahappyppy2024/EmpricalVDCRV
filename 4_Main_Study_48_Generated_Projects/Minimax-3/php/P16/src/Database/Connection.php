<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

/**
 * Singleton-style SQLite connection holder.
 * The PDO instance is created lazily on first access and reused.
 */
final class Connection
{
    private static ?PDO $pdo = null;
    private static string $path = '';

    public static function configure(string $path): void
    {
        if (self::$pdo !== null && self::$path === $path) {
            return;
        }
        self::$pdo = null;
        self::$path = $path;
    }

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        if (self::$path === '') {
            throw new RuntimeException('Database path not configured. Call Connection::configure() first.');
        }
        $dir = dirname(self::$path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . self::$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$path = '';
    }

    public static function path(): string
    {
        return self::$path;
    }
}