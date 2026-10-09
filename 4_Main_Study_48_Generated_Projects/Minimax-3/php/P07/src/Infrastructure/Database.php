<?php
declare(strict_types=1);
namespace App\Infrastructure;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = Config::get('DB_PATH') ?: 'var/db/cloud.sqlite';
            if (!is_dir(dirname($path))) {
                @mkdir(dirname($path), 0777, true);
            }
            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return self::$pdo;
    }

    public static function migrate(): void
    {
        $schema = require __DIR__ . '/../../database/schema.php';
        self::pdo()->exec($schema);
    }

    public static function reset(): void
    {
        $path = Config::get('DB_PATH') ?: 'var/db/cloud.sqlite';
        foreach ([$path, $path . '-wal', $path . '-shm'] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        self::$pdo = null;
        self::migrate();
    }
}