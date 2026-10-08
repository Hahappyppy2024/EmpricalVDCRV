<?php

declare(strict_types=1);

use CloudFS\Config;
use CloudFS\Database\Database;
use CloudFS\Database\Seeder;
use CloudFS\Services\AuditService;
use CloudFS\Services\StorageService;

define('CLOUDFS_ROOT', dirname(__DIR__));
require CLOUDFS_ROOT . '/vendor/autoload.php';

$config = Config::load(CLOUDFS_ROOT);

$dbPath = $config['db_path'];
echo "Resetting database at: $dbPath\n";

$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
if (file_exists($dbPath)) {
    unlink($dbPath);
}
foreach (glob($dbPath . '-*') ?: [] as $sidecar) {
    unlink($sidecar);
}

$storageDir = $config['storage_path'];
if (is_dir($storageDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storageDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
}
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

$db = new Database($dbPath);
$schemaFile = CLOUDFS_ROOT . '/src/Database/schema.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "Schema file not found: $schemaFile\n");
    exit(1);
}
$db->pdo()->exec((string) file_get_contents($schemaFile));
echo "Schema applied.\n";

$storage = new StorageService($storageDir);
$audit = new AuditService($db);
$seeder = new Seeder($db, $storage, $audit);
$accounts = $seeder->seed();

echo "Seed data inserted.\n";
echo "Seed accounts:\n";
foreach ($accounts as $name => $username) {
    $password = $name === 'admin' ? 'admin123' : 'password123';
    echo "  - $username / $password\n";
}
echo "Database reset complete.\n";
