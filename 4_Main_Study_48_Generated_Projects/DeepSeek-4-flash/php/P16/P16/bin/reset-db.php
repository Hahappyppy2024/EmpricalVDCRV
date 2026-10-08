<?php

declare(strict_types=1);

/**
 * Database reset command.
 *
 *   php bin/reset-db.php
 *
 * Drops the SQLite database file, re-applies database/schema.sql and runs the
 * deterministic seed fixture (database/seed.php).
 */

use App\Config;
use App\Database;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$config = new Config(array_merge(getenv() ?: [], $_ENV));
$path = $config->get('db.path');

if (is_file($path)) {
    unlink($path);
    echo "Removed existing database: $path\n";
}

// Touch the database so the path exists, then seed (schema is applied by seed.php).
$db = new Database($path);
$db->pdo()->query('SELECT 1');

require $root . '/database/seed.php';

echo "Database reset complete.\n";
