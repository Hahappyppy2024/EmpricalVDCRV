<?php

declare(strict_types=1);

/**
 * Reset and reseed the SQLite database.
 *
 * Usage: php scripts/seed.php
 *
 * Wipes all tables and re-applies the deterministic seed fixtures.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

use P13\Config;
use P13\Database;
use P13\Schema;

$config = new Config();
$db = new Database($config);
$pdo = $db->pdo();
$schema = new Schema();

$pdo->exec('PRAGMA foreign_keys = OFF');
$pdo->exec('BEGIN');

$schema->migrate($pdo);

$tables = [
    'frontend_api_errors',
    'import_export',
    'audit_events',
    'quarantine',
    'mail_rules',
    'contacts',
    'attachment_handling',
    'stored_files',
    'message_reading',
    'message_compose',
    'messages',
    'folders',
    'account_access',
    'sessions',
    'users',
    'domains',
];
foreach ($tables as $table) {
    $pdo->exec('DELETE FROM ' . $table);
}
$pdo->exec('DELETE FROM sqlite_sequence');

$schema->seed($db, $config);

$pdo->exec('COMMIT');
$pdo->exec('PRAGMA foreign_keys = ON');

echo 'Database reset and seeded.' . PHP_EOL;
echo 'Database file: ' . $config->get('database.path') . PHP_EOL;
echo 'Seed accounts: alice / ' . $config->get('seed.password') . PHP_EOL;
