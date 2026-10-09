<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Database;
use App\Schema;
use App\Seeder;

$rootPath = dirname(__DIR__);
$config = Config::load($rootPath);

echo "== Conference Review System — Smoke Check ==\n\n";

echo "[1/3] Resetting and seeding the database...\n";
Database::init($config['db']['path']);
Database::reset();
Database::init($config['db']['path']);
Schema::migrate(Database::pdo());
Seeder::seed(Database::pdo());

$pdo = Database::pdo();
$counts = [
    'users'              => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'phases'             => (int)$pdo->query('SELECT COUNT(*) FROM conference_phases')->fetchColumn(),
    'paper_submissions'  => (int)$pdo->query('SELECT COUNT(*) FROM paper_submissions')->fetchColumn(),
    'reviewer_assignments' => (int)$pdo->query('SELECT COUNT(*) FROM reviewer_assignments')->fetchColumn(),
    'reviews'            => (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
    'rebuttals'          => (int)$pdo->query('SELECT COUNT(*) FROM rebuttals')->fetchColumn(),
    'decisions'          => (int)$pdo->query('SELECT COUNT(*) FROM decisions')->fetchColumn(),
    'audit_events'       => (int)$pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn(),
];
echo "Seed entity counts:\n";
foreach ($counts as $k => $v) echo "  - $k: $v\n";

echo "\n[2/3] Verifying password hash...\n";
$u = $pdo->query("SELECT username FROM users WHERE username = 'chair1'")->fetch();
$h = $pdo->query("SELECT password_hash FROM users WHERE username = 'chair1'")->fetchColumn();
echo $u ? "  - chair1 found\n" : "  - chair1 NOT FOUND\n";
echo password_verify('Password123!', $h) ? "  - password verify OK\n" : "  - password verify FAILED\n";

echo "\n[3/3] Confirming template and storage paths exist...\n";
$paths = [
    $rootPath . '/templates/home.php',
    $rootPath . '/templates/layout.php',
    $rootPath . '/public/static/app.css',
    $rootPath . '/public/static/app.js',
    $rootPath . '/storage/database.sqlite',
    $rootPath . '/storage/uploads',
    $rootPath . '/storage/exports',
];
foreach ($paths as $p) {
    $abs = realpath($p);
    echo "  - $p : " . ($abs ? "OK" : "MISSING") . "\n";
}

echo "\nSmoke check complete. Run: php -S 127.0.0.1:8080 -t public public/index.php\n";
echo "Then visit http://127.0.0.1:8080/ — sign in with chair1 / Password123!\n";