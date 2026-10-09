<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_dir($root . '/storage')) mkdir($root . '/storage', 0775, true);
if (!is_dir($root . '/storage/data')) mkdir($root . '/storage/data', 0775, true);
if (!is_dir($root . '/storage/uploads')) mkdir($root . '/storage/uploads', 0775, true);
if (!is_dir($root . '/storage/backups')) mkdir($root . '/storage/backups', 0775, true);

$envPath = $root . '/.env';
$envExample = $root . '/.env.example';
if (!file_exists($envPath) && file_exists($envExample)) {
    copy($envExample, $envPath);
}

$dotenv = file_exists($envPath) ? file_get_contents($envPath) : file_get_contents($envExample);
foreach (explode("\n", $dotenv) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;
    if (!str_contains($line, '=')) continue;
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    $v = trim($v, "\"' \t");
    if (getenv($k) === false) putenv("$k=$v");
    $_ENV[$k] = $v;
}

$dbPath = getenv('DB_PATH') ?: 'storage/data/panel.sqlite';
if (!str_starts_with($dbPath, DIRECTORY_SEPARATOR) && !preg_match('#^[A-Za-z]:[\\\\/]#', $dbPath)) {
    $dbPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dbPath);
}

if (file_exists($dbPath)) unlink($dbPath);

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON;');

$schema = file_get_contents($root . '/db/schema.sql');
$pdo->exec($schema);

$seed = file_get_contents($root . '/db/seed.sql');

// Replace placeholder bcrypt hash for users with a real hash for the deterministic seed password
$passwordHash = password_hash('Password123!', PASSWORD_BCRYPT);
// Build a regex-tolerant placeholder matching the seed INSERTs.
$placeholder = "/'\\\$2y\\\$10\\\$VUDv7zGYq7t\\.ZM0cO8D9COyD7lCuA9y\\/OUHrNsj4q\\.cRW6B5rEXi\\.'/";
// Use str_replace since the placeholder is a literal string. preg_replace's
// replacement interprets $1, $2, $0, etc. and would corrupt the bcrypt hash.
$seed = str_replace(
    "'" . '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.' . "'",
    "'" . $passwordHash . "'",
    $seed
);

$pdo->exec($seed);

echo "Database initialized at: $dbPath\n";
echo "Users (password: 'Password123!'):\n";
echo "  - admin   (role: admin)\n";
echo "  - support (role: support)\n";
echo "  - alice   (role: customer)\n";
echo "  - bob     (role: customer)\n";
echo "  - charlie (role: customer)\n";