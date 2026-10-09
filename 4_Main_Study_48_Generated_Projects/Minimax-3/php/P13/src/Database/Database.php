<?php
declare(strict_types=1);

namespace MailServer\Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $path = getenv('APP_DB_PATH') ?: __DIR__ . '/../../storage/app.db';
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            try {
                self::$pdo = new PDO('sqlite:' . $path);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA journal_mode = WAL');
            } catch (PDOException $e) {
                throw new PDOException('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        $path = getenv('APP_DB_PATH') ?: __DIR__ . '/../../storage/app.db';
        if (file_exists($path)) {
            unlink($path);
        }
        $wal = $path . '-wal';
        $shm = $path . '-shm';
        if (file_exists($wal)) {
            unlink($wal);
        }
        if (file_exists($shm)) {
            unlink($shm);
        }
        self::$pdo = null;
        self::connection();
    }
}