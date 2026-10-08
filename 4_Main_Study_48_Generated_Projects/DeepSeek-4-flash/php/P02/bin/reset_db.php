<?php

declare(strict_types=1);

/**
 * Reset the database: drop the SQLite file, apply the schema, run the seed.
 * Usage: php bin/reset_db.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = App\Config\Config::load($root);
$dbPath = $config['db_path'];

foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$pdo = App\Repositories\Database::connect($dbPath);
$schema = file_get_contents($root . '/database/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Could not read schema file.\n");
    exit(1);
}
$pdo->exec($schema);
echo "Schema applied to " . $dbPath . "\n";

require $root . '/database/seed.php';
seed_database($root);

echo "Database reset complete.\n";
