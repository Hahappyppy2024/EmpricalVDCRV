<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Seeder;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);
if (file_exists($rootDir . '/.env')) {
    Dotenv::createImmutable($rootDir)->safeLoad();
}

$dbPath = (string) (getenv('DB_PATH') ?: $rootDir . '/data/hosting.db');
$storageDir = (string) (getenv('STORAGE_DIR') ?: $rootDir . '/storage');

echo "Resetting database at {$dbPath}\n";
if (file_exists($dbPath)) {
    unlink($dbPath);
}

foreach (['files', 'backups', 'restore_logs'] as $sub) {
    $dir = $storageDir . '/' . $sub;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    }
}
echo "Storage reset at {$storageDir}\n";

Connection::configure($dbPath);
$seeder = new Seeder($storageDir);
$seeder->seed();

echo "Database reset and seeded.\n";
echo "Seed accounts:\n";
echo "  admin  / admin@example.com / Admin@123 (role: admin)\n";
echo "  support / support@example.com / Support@123 (role: support)\n";
echo "  alice  / alice@example.com / Alice@123 (role: customer)\n";
echo "  bob    / bob@example.com / Bob@123 (role: customer)\n";
