<?php
declare(strict_types=1);

/**
 * bin/init-db.php
 *
 * Resets (deletes) the SQLite database, applies the schema, and loads
 * deterministic seed fixtures for every actor, role, and workflow state
 * required by the LMS acceptance criteria.
 *
 * Usage:
 *   php bin/init-db.php
 *
 * Optional:
 *   DB_PATH=storage/lms.sqlite php bin/init-db.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "init-db must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (class_exists(Dotenv\Dotenv::class)) {
    $envPath = $root . '/.env';
    if (is_file($envPath)) {
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
}

$config = require $root . '/config/settings.php';

$dbPath = $config['db']['path'];
if (!str_starts_with($dbPath, DIRECTORY_SEPARATOR)) {
    $dbPath = $root . DIRECTORY_SEPARATOR . $dbPath;
}

if (is_file($dbPath)) {
    unlink($dbPath);
}
if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0775, true);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON;');
$pdo->exec('PRAGMA journal_mode = WAL;');

$schema = file_get_contents($root . '/database/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Could not read schema.sql\n");
    exit(1);
}
$pdo->exec($schema);

$pdo->beginTransaction();

/* --- Users --- */
$users = [
    ['admin1',     'admin@lms.test',     'Site Admin',         'admin',     'Admin!Pass1'],
    ['inst_anna',  'anna@lms.test',      'Dr. Anna Walker',    'instructor','Instructor!Pass1'],
    ['inst_brian', 'brian@lms.test',     'Dr. Brian Cole',     'instructor','Instructor!Pass1'],
    ['inst_carla', 'carla@lms.test',     'Dr. Carla Diaz',     'instructor','Instructor!Pass1'],
    ['stu_olivia', 'olivia@lms.test',    'Olivia Park',        'student',   'Student!Pass1'],
    ['stu_peter',  'peter@lms.test',     'Peter Smith',        'student',   'Student!Pass1'],
    ['stu_quinn',  'quinn@lms.test',     'Quinn Johnson',      'student',   'Student!Pass1'],
    ['stu_ruby',   'ruby@lms.test',      'Ruby Adams',         'student',   'Student!Pass1'],
    ['stu_sam',    'sam@lms.test',       'Sam Brown',          'student',   'Student!Pass1'],
    ['stu_tara',   'tara@lms.test',      'Tara Wilson',        'student',   'Student!Pass1'],
];

$userIds = [];
$insertUser = $pdo->prepare(
    'INSERT INTO users (username, email, full_name, password_hash, role, status)
     VALUES (?, ?, ?, ?, ?, "active")'
);
foreach ($users as [$u, $email, $name, $role, $pw]) {
    $insertUser->execute([
        $u,
        $email,
        $name,
        password_hash($pw, PASSWORD_BCRYPT),
        $role,
    ]);
    $userIds[$u] = (int)$pdo->lastInsertId();
}

/* --- Courses --- */
$courses = [
    ['CS101', 'Intro to Computer Science', 'Foundations of computing.', 'Computer Science', '2026-Fall', 'inst_anna',  'open'],
    ['MATH201','Discrete Mathematics',     'Logic, sets, combinatorics.','Mathematics',     '2026-Fall', 'inst_brian', 'open'],
    ['ENG110','Academic Writing',          'Writing for university.',  'Humanities',      '2026-Spring','inst_carla', 'closed'],
    ['PHYS150','Classical Mechanics',      'Newtonian physics primer.', 'Physics',         '2026-Fall', 'inst_anna',  'hidden'],
    ['CS220', 'Data Structures',           'Lists, trees, graphs.',    'Computer Science','2026-Spring','inst_brian', 'open'],
];

$courseIds = [];
$insertCourse = $pdo->prepare(
    'INSERT INTO courses (code, title, description, category, semester, instructor_id, visibility)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
foreach ($courses as [$code, $title, $desc, $cat, $sem, $instructor, $vis]) {
    $insertCourse->execute([$code, $title, $desc, $cat, $sem, $userIds[$instructor], $vis]);
    $courseIds[$code] = (int)$pdo->lastInsertId();
}

/* --- Enrollments --- */
$enrollments = [
    ['stu_olivia', 'CS101', 'student'],
    ['stu_peter',  'CS101', 'student'],
    ['stu_quinn',  'CS101', 'student'],
    ['stu_ruby',   'CS101', 'student'],
    ['stu_sam',    'MATH201','student'],
    ['stu_tara',   'MATH201','student'],
    ['stu_olivia', 'MATH201','student'],
    ['stu_peter',  'CS220', 'student'],
    ['stu_quinn',  'CS220', 'student'],
    ['stu_ruby',   'ENG110','student'],
    ['stu_sam',    'ENG110','student'],
];
$insertEnroll = $pdo->prepare(
    'INSERT INTO enrollments (user_id, course_id, role, status)
     VALUES (?, ?, ?, "active")'
);
foreach ($enrollments as [$stu, $course, $role]) {
    $insertEnroll->execute([$userIds[$stu], $courseIds[$course], $role]);
}

/* --- Materials --- */
$materialsDir = $root . DIRECTORY_SEPARATOR . $config['storage']['uploads'];
if (!is_dir($materialsDir)) {
    mkdir($materialsDir, 0775, true);
}
$seedFile = $materialsDir . '/cs101-syllabus.txt';
if (!is_file($seedFile)) {
    file_put_contents(
        $seedFile,
        "CS101 - Intro to Computer Science\nSyllabus overview (seed fixture).\n"
    );
}
$insertMaterial = $pdo->prepare(
    'INSERT INTO materials (course_id, title, description, filename, original_name, mime, size, uploaded_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$insertMaterial->execute([
    $courseIds['CS101'], 'Course Syllabus', 'Syllabus document',
    'cs101-syllabus.txt', 'cs101-syllabus.txt', 'text/plain',
    filesize($seedFile), $userIds['inst_anna'],
]);
$insertMaterial->execute([
    $courseIds['MATH201'], 'Mathematical Notation', 'Reference notes',
    'cs101-syllabus.txt', 'notation.txt', 'text/plain',
    filesize($seedFile), $userIds['inst_brian'],
]);

/* --- Announcements --- */
$insertAn = $pdo->prepare(
    'INSERT INTO announcements (course_id, title, body, posted_by) VALUES (?, ?, ?, ?)'
);
$insertAn->execute([
    $courseIds['CS101'],
    'Welcome to CS101',
    'Office hours are Tuesdays 2-4pm.',
    $userIds['inst_anna'],
]);
$insertAn->execute([
    $courseIds['CS101'],
    'Lab 1 posted',
    'Lab 1 is available under Materials.',
    $userIds['inst_anna'],
]);
$insertAn->execute([
    $courseIds['MATH201'],
    'Textbook update',
    'We are using the 3rd edition.',
    $userIds['inst_brian'],
]);

/* --- Discussions --- */
$insertDisc = $pdo->prepare(
    'INSERT INTO discussions (course_id, parent_id, author_id, body) VALUES (?, ?, ?, ?)'
);
$insertDisc->execute([$courseIds['CS101'], null, $userIds['inst_anna'], 'Welcome! Introduce yourself.']);
$rootId = (int)$pdo->lastInsertId();
$insertDisc->execute([$courseIds['CS101'], $rootId, $userIds['stu_olivia'], 'Hi, I am Olivia.']);
$insertDisc->execute([$courseIds['CS101'], $rootId, $userIds['stu_peter'],  'Hello from Peter.']);
$insertDisc->execute([$courseIds['MATH201'], null, $userIds['inst_brian'], 'Syllabus question thread.']);

/* --- Assignments --- */
$insertAssign = $pdo->prepare(
    'INSERT INTO assignments (course_id, title, instructions, due_at, max_score, created_by)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$insertAssign->execute([
    $courseIds['CS101'], 'Hello World Lab',
    'Submit a working hello world program.',
    '2026-12-15 23:59:00', 100, $userIds['inst_anna'],
]);
$assign1 = (int)$pdo->lastInsertId();
$insertAssign->execute([
    $courseIds['CS101'], 'Logic Worksheet',
    'Complete the truth-table exercise.',
    '2026-11-30 23:59:00', 50, $userIds['inst_anna'],
]);
$assign2 = (int)$pdo->lastInsertId();
$insertAssign->execute([
    $courseIds['MATH201'], 'Set Theory Problem Set',
    'Problems from chapter 2.',
    '2026-12-20 23:59:00', 100, $userIds['inst_brian'],
]);

/* --- Submissions --- */
$subFile = $materialsDir . '/cs101-syllabus.txt';
$insertSub = $pdo->prepare(
    'INSERT INTO submissions (assignment_id, student_id, filename, original_name, mime, size, comment, status, score, feedback, graded_by, graded_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))'
);
$insertSub->execute([
    $assign1, $userIds['stu_olivia'], 'cs101-syllabus.txt', 'olivia-hello.txt',
    'text/plain', filesize($subFile), 'My first program.', 'graded', 92.0, 'Good work.', $userIds['inst_anna'],
]);
$insertSub->execute([
    $assign2, $userIds['stu_peter'], 'cs101-syllabus.txt', 'peter-logic.txt',
    'text/plain', filesize($subFile), 'Truth tables done.', 'submitted', null, null, null,
]);

/* --- Quizzes --- */
$insertQuiz = $pdo->prepare(
    'INSERT INTO quizzes (course_id, title, description, created_by, opens_at, closes_at, max_score)
     VALUES (?, ?, ?, ?, datetime(\'now\'), ?, ?)'
);
$insertQuiz->execute([
    $courseIds['CS101'], 'CS101 Knowledge Check',
    'Three quick questions.', $userIds['inst_anna'], '2027-01-31 23:59:00', 3,
]);
$quiz1 = (int)$pdo->lastInsertId();

$insertQ = $pdo->prepare(
    'INSERT INTO questions (quiz_id, prompt, kind, options_json, correct_json, points, position)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$insertQ->execute([
    $quiz1, 'Which language is this LMS implemented in?',
    'single',
    json_encode(['JavaScript', 'PHP', 'Python', 'Ruby']),
    json_encode(['PHP']),
    1, 1,
]);
$insertQ->execute([
    $quiz1, 'Select all primitive types in PHP.',
    'multi',
    json_encode(['string', 'class', 'int', 'object']),
    json_encode(['string', 'int']),
    1, 2,
]);
$insertQ->execute([
    $quiz1, 'Name one HTTP method supported by Slim 4.',
    'short',
    json_encode([]),
    json_encode(['GET']),
    1, 3,
]);

/* --- Grades --- */
$insertGrade = $pdo->prepare(
    'INSERT INTO grades (course_id, student_id, item_type, item_id, item_label, score, max_score, feedback, graded_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insertGrade->execute([
    $courseIds['CS101'], $userIds['stu_olivia'], 'assignment', $assign1, 'Hello World Lab',
    92.0, 100.0, 'Good work.', $userIds['inst_anna'],
]);
$insertGrade->execute([
    $courseIds['CS101'], $userIds['stu_peter'], 'manual', null, 'Class participation',
    85.0, 100.0, 'Active in class.', $userIds['inst_anna'],
]);

/* --- Settings (LMS-12) --- */
$insertSetting = $pdo->prepare(
    'INSERT INTO settings (key, value) VALUES (?, ?)'
);
$insertSetting->execute(['site_name', 'P01 Learning Management System']);
$insertSetting->execute(['allow_self_enroll', 'true']);
$insertSetting->execute(['max_upload_bytes', '10485760']);
$insertSetting->execute(['default_semester', '2026-Fall']);

/* --- Audit events (LMS-12) --- */
$insertAudit = $pdo->prepare(
    'INSERT INTO audit_events (actor_id, action, target_type, target_id, payload_json)
     VALUES (?, ?, ?, ?, ?)'
);
$insertAudit->execute([$userIds['admin1'], 'system.seed', 'system', null, json_encode(['source' => 'init-db'])]);

$pdo->commit();

fwrite(STDOUT, "Database initialized at: {$dbPath}\n");
fwrite(STDOUT, 'Seed accounts (username / password):' . "\n");
fwrite(STDOUT, "  admin1 / Admin!Pass1\n");
fwrite(STDOUT, "  inst_anna / Instructor!Pass1\n");
fwrite(STDOUT, "  inst_brian / Instructor!Pass1\n");
fwrite(STDOUT, "  inst_carla / Instructor!Pass1\n");
fwrite(STDOUT, "  stu_olivia / Student!Pass1\n");
fwrite(STDOUT, "  stu_peter / Student!Pass1\n");
fwrite(STDOUT, "  stu_quinn / Student!Pass1\n");
fwrite(STDOUT, "  stu_ruby / Student!Pass1\n");
fwrite(STDOUT, "  stu_sam / Student!Pass1\n");
fwrite(STDOUT, "  stu_tara / Student!Pass1\n");
