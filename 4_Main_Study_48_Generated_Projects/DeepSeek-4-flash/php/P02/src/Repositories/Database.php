<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class Database
{
    public static function connect(string $dbPath): PDO
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        return $pdo;
    }
}
