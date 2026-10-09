<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MailServer\Database\Database;
use MailServer\Database\Migrations;
use MailServer\Database\Seed;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

Database::reset();
Migrations::run();
Seed::run();

echo "[reset] Database reset and reseeded successfully.\n";