<?php
declare(strict_types=1);
namespace App;
use PDO;
final class Database
{
    public static function connect(string $path): PDO
    {
        $absolute = str_starts_with($path, '/') ? $path : dirname(__DIR__) . '/' . $path;
        $dir = dirname($absolute); if (!is_dir($dir)) mkdir($dir, 0775, true);
        $db = new PDO('sqlite:' . $absolute, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000');
        return $db;
    }
}
