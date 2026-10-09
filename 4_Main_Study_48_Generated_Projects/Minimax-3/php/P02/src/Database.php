<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;
    private static string $path = '';

    public static function init(string $dbPath): void
    {
        self::$path = $dbPath;
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        self::$instance = null;
    }

    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            try {
                $pdo = new PDO('sqlite:' . self::$path);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->exec('PRAGMA foreign_keys = ON;');
                self::$instance = $pdo;
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        if (file_exists(self::$path)) {
            unlink(self::$path);
        }
        self::$instance = null;
    }

    public static function path(): string
    {
        return self::$path;
    }
}
