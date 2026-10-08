<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Shop\Config;
use Shop\Database;

Config::load(dirname(__DIR__));

$pdo = Database::connect();
Database::migrate($pdo);

if (Database::hasUsers($pdo)) {
    echo "Database already seeded. Run \"php bin/reset_db.php\" to reset and re-seed.\n";
    exit(0);
}

Database::seed($pdo);
echo "Database seeded.\n";
