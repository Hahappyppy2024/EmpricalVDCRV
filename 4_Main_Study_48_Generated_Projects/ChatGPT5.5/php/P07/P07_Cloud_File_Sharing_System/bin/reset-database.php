<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Seeder;

$root = dirname(__DIR__);
$dbPath = getenv('DB_PATH') ?: 'var/cloud_files.sqlite';
$uploadDir = getenv('UPLOAD_DIR') ?: 'var/uploads';
Seeder::reset($root, $dbPath, $uploadDir);
echo "Database reset and seeded.\n";
