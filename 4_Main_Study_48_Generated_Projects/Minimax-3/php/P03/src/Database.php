<?php
declare(strict_types=1);

namespace Shop;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $path = Config::get('DB_PATH', 'data/shop.sqlite');
        if (!str_starts_with($path, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $path;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $dsn = 'sqlite:' . $path;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
        self::$pdo = $pdo;
        return $pdo;
    }

    public static function migrate(): void
    {
        $pdo = self::pdo();
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        if ($schema === false) {
            throw new PDOException('schema.sql not readable');
        }
        $pdo->exec($schema);
    }

    public static function reset(): void
    {
        $path = Config::get('DB_PATH', 'data/shop.sqlite');
        if (!str_starts_with($path, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $path;
        }
        foreach ([$path, $path . '-wal', $path . '-shm', $path . '-journal'] as $candidate) {
            if (file_exists($candidate)) {
                @unlink($candidate);
            }
        }
        self::$pdo = null;
        self::migrate();
    }

    public static function transactional(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
