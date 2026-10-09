<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Database;
use App\Schema;
use App\Seeder;

$rootPath = dirname(__DIR__);
$config = Config::load($rootPath);

echo "Resetting database at: {$config['db']['path']}\n";

Database::init($config['db']['path']);
Database::reset();
Database::init($config['db']['path']);

$pdo = Database::pdo();
Schema::migrate($pdo);
Seeder::seed($pdo);

echo "Database reset and seeded successfully.\n";
echo "Seed accounts (password: Password123!):\n";
echo "  admin / admin@conference.local\n";
echo "  chair1 / chair@conference.local\n";
echo "  reviewer1 / reviewer1@conference.local\n";
echo "  reviewer2 / reviewer2@conference.local\n";
echo "  reviewer3 / reviewer3@conference.local\n";
echo "  author1 / author1@conference.local\n";
echo "  author2 / author2@conference.local\n";
echo "  author3 / author3@conference.local\n";