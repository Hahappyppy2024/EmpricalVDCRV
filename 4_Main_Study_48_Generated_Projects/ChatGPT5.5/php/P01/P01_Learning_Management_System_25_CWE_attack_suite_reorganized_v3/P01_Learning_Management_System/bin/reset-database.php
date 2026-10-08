<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Env;
use App\Seeder;

$root = dirname(__DIR__);
Env::load($root . '/.env');
$relative = $_ENV['DB_PATH'] ?? getenv('DB_PATH') ?: 'var/lms.sqlite';
$path = str_starts_with($relative, '/') ? $relative : $root . '/' . $relative;
if (in_array('--if-missing', $argv, true) && is_file($path)) {
    echo "Database already exists: {$path}\n";
    exit(0);
}
$pdo = Seeder::reset($root, $relative);
echo "Database reset and seeded: {$path}\n";
