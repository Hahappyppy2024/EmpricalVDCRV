<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Shop\Config;
use Shop\Database;

Config::load(dirname(__DIR__));

$path = Config::absolute((string) Config::get('DB_PATH', 'data/shop.db'));
foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

$pdo = Database::connect();
Database::migrate($pdo);
Database::seed($pdo);

echo "Database reset and seeded: {$path}\n";
echo "Seed accounts:\n";
echo "  admin@example.com / admin123\n";
echo "  seller1@example.com / seller123\n";
echo "  seller2@example.com / seller123\n";
echo "  customer1@example.com / customer123\n";
echo "  customer2@example.com / customer123\n";
echo "  moderator1@example.com / moderator123\n";
