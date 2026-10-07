<?php
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dbDir = APP_BASE . '/data';
    if (!is_dir($dbDir)) mkdir($dbDir, 0777, true);
    $dbPath = $dbDir . '/app.db';

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->query("PRAGMA table_info($table)");
    foreach (($stmt ? $stmt->fetchAll() : []) as $col) {
        if (($col['name'] ?? '') === $column) return;
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $definition");
}

function init_db(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL,
        created_at TEXT NOT NULL,
        is_global_admin INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        course_id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS memberships (
        membership_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        role_in_course TEXT NOT NULL,
        joined_at TEXT NOT NULL,
        UNIQUE(course_id, user_id),
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        post_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        body TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(author_id) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        body TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(author_id) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
        assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        created_by INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        due_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS uploads (
        upload_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        uploaded_by INTEGER NOT NULL,
        original_name TEXT NOT NULL,
        storage_path TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(uploaded_by) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
        assignment_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        content_text TEXT NOT NULL DEFAULT '',
        attachment_upload_id INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(assignment_id, student_id),
        FOREIGN KEY(assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY(attachment_upload_id) REFERENCES uploads(upload_id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
        question_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        created_by INTEGER NOT NULL,
        qtype TEXT NOT NULL,
        prompt TEXT NOT NULL,
        options_json TEXT,
        answer_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quizzes (
        quiz_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        created_by INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        open_at TEXT,
        due_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
        quiz_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        PRIMARY KEY(quiz_id, question_id),
        FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
        FOREIGN KEY(question_id) REFERENCES questions(question_id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
        attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        started_at TEXT NOT NULL,
        submitted_at TEXT,
        score REAL,
        UNIQUE(quiz_id, student_id),
        FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_answers (
        attempt_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        answer_json TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        PRIMARY KEY(attempt_id, question_id),
        FOREIGN KEY(attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
        FOREIGN KEY(question_id) REFERENCES questions(question_id) ON DELETE CASCADE
    )");

    ensure_column($pdo, 'users', 'is_global_admin', 'is_global_admin INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'uploads', 'storage_path', 'storage_path TEXT NOT NULL DEFAULT ""');
    ensure_column($pdo, 'quiz_answers', 'updated_at', 'updated_at TEXT NOT NULL DEFAULT ""');
}


init_db(db());
seed_admin(db());