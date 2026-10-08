<?php
declare(strict_types=1);
namespace App;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }
}
