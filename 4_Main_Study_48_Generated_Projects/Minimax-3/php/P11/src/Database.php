<?php
declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;
    private static ?string $dbPath = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = self::path();
            if (!file_exists($path)) {
                throw new RuntimeException("Database not initialized at $path. Run: php bin/init-db.php");
            }
            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA foreign_keys = ON;');
        }
        return self::$pdo;
    }

    public static function path(): string
    {
        if (self::$dbPath !== null) return self::$dbPath;
        $raw = getenv('DB_PATH') ?: 'storage/data/panel.sqlite';
        if (!str_starts_with($raw, DIRECTORY_SEPARATOR) && !preg_match('#^[A-Za-z]:[\\\\/]#', $raw)) {
            $raw = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        }
        return self::$dbPath = $raw;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}