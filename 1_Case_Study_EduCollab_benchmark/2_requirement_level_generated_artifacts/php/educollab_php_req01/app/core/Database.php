<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dbPath = $config['db_path'];
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        self::$pdo = new PDO('sqlite:' . $dbPath);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');

        return self::$pdo;
    }
}
