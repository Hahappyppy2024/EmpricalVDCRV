#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\App;
use App\Database\Connection;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$opts = getopt('', ['reset', 'profile::', 'fresh']);
$reset = isset($opts['reset']) || isset($opts['fresh']);

if ($reset) {
    echo "[seed] Resetting SQLite database and reapplying schema...\n";
    App::resetDatabase();
    echo "[seed] Reset complete.\n";
    exit(0);
}

echo "[seed] Ensuring database and fixtures exist...\n";
App::boot();
echo "[seed] Database path: " . Connection::path() . PHP_EOL;
echo "[seed] Done.\n";