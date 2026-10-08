<?php

declare(strict_types=1);

/**
 * Database reset + seed.
 *
 * Usage: php bin/reset.php [--drop]
 *   Default: applies the schema (CREATE TABLE IF NOT EXISTS) and seeds
 *            deterministic fixtures. Existing user-created data is preserved
 *            unless --drop is passed, in which case the database file is
 *            deleted and rebuilt from scratch.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$drop = in_array('--drop', $argv ?? [], true);
$root = dirname(__DIR__);
$config = new App\Config($root);

if ($drop) {
    $dbFile = $config->dbPath();
    if (is_file($dbFile)) {
        @unlink($dbFile);
        echo "Dropped database file: {$dbFile}\n";
    }
    foreach ([$config->uploadDir(), $config->exportDir()] as $dir) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (!str_ends_with($file, '.gitkeep')) {
                @unlink($file);
            }
        }
    }
}

$db = new App\Database($config);
App\Schema::apply($db);

// Re-seed idempotently: clear seed rows first so a reset run is deterministic.
$db->execute('DELETE FROM audit_events');
$db->execute('DELETE FROM account_access_log');
$db->execute('DELETE FROM realtime_events');
$db->execute('DELETE FROM settings');
$db->execute('DELETE FROM reports');
$db->execute('DELETE FROM exports');
$db->execute('DELETE FROM grades');
$db->execute('DELETE FROM grade_items');
$db->execute('DELETE FROM quiz_answers');
$db->execute('DELETE FROM quiz_attempts');
$db->execute('DELETE FROM quiz_questions');
$db->execute('DELETE FROM quizzes');
$db->execute('DELETE FROM submissions');
$db->execute('DELETE FROM assignments');
$db->execute('DELETE FROM discussions');
$db->execute('DELETE FROM announcements');
$db->execute('DELETE FROM materials');
$db->execute('DELETE FROM enrollments');
$db->execute('DELETE FROM courses');
$db->execute('DELETE FROM password_resets');
$db->execute('DELETE FROM sessions');
$db->execute('DELETE FROM users');
$db->execute('DELETE FROM roles');
$db->execute('DELETE FROM sqlite_sequence WHERE name IN ("roles","users","sessions","password_resets","account_access_log","courses","enrollments","materials","announcements","discussions","assignments","submissions","quizzes","quiz_questions","quiz_attempts","quiz_answers","grade_items","grades","exports","reports","audit_events","realtime_events")');

$seeder = new App\Seeder($db, $config);
$seeder->seed();

$counts = [];
foreach (['users', 'courses', 'enrollments', 'materials', 'announcements', 'discussions', 'assignments', 'submissions', 'quizzes', 'grade_items', 'grades'] as $table) {
    $counts[$table] = (int) $db->first("SELECT COUNT(*) AS c FROM {$table}")['c'];
}

echo "Database reset + seeded at: " . $config->dbPath() . "\n";
foreach ($counts as $table => $count) {
    printf("  %-16s %d\n", $table, $count);
}
echo "Seed accounts:\n";
foreach (['admin', 'alice', 'bob', 'student1', 'student2', 'student3', 'guest'] as $u) {
    printf("  %-10s %s\n", $u, match ($u) {
        'admin' => 'AdminPass123!',
        'alice', 'bob' => 'InstructorPass123!',
        'student1', 'student2', 'student3' => 'StudentPass123!',
        'guest' => 'GuestPass123!',
    });
}
