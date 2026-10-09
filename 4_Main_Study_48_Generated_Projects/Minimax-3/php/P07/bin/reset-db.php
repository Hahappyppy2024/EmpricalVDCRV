<?php
declare(strict_types=1);

use App\Infrastructure\Database;
use App\Infrastructure\Storage;
use App\Infrastructure\Config;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Infrastructure/Database.php';
require __DIR__ . '/../src/Infrastructure/Storage.php';
require __DIR__ . '/../src/Infrastructure/Config.php';
require __DIR__ . '/../database/seed.php';

Database::reset();
Storage::init();
seed(Database::pdo());
echo "Database reset and seeded successfully.\n";