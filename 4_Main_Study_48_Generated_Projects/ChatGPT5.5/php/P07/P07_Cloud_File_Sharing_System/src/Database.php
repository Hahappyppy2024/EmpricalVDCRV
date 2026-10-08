<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $db = new PDO('sqlite:' . $path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON');
        return $db;
    }
}
