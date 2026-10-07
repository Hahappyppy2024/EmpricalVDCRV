<?php
define('APP_BASE', dirname(__DIR__));
require_once APP_BASE . '/src/helpers.php';

// Sessions
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dbFile = APP_BASE . '/data/app.db';
    if (!is_dir(dirname($dbFile))) {
        mkdir(dirname($dbFile), 0777, true);
    }

    $firstInit = !file_exists($dbFile);
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($firstInit) {
        init_db($pdo);
    }
    ensure_schema_compat($pdo);
    seed_admin($pdo);

    return $pdo;
}

function init_db(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL,
        is_global_admin INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
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
        created_at TEXT NOT NULL,
        UNIQUE(course_id, user_id),
        FOREIGN KEY(course_id) REFERENCES courses(course_id),
        FOREIGN KEY(user_id) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        post_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        body TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id),
        FOREIGN KEY(author_id) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        body TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id),
        FOREIGN KEY(post_id) REFERENCES posts(post_id),
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
        FOREIGN KEY(course_id) REFERENCES courses(course_id),
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
        FOREIGN KEY(uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
        assignment_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        content_text TEXT NOT NULL DEFAULT '',
        attachment_upload_id INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(assignment_id, student_id),
        FOREIGN KEY(assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
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
        FOREIGN KEY(course_id) REFERENCES courses(course_id),
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
        FOREIGN KEY(course_id) REFERENCES courses(course_id),
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
        quiz_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        points INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (quiz_id, question_id),
        FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id),
        FOREIGN KEY(question_id) REFERENCES questions(question_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
        attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        started_at TEXT NOT NULL,
        submitted_at TEXT,
        score REAL,
        UNIQUE(quiz_id, student_id),
        FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id),
        FOREIGN KEY(student_id) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_answers (
        answer_id INTEGER PRIMARY KEY AUTOINCREMENT,
        attempt_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        answer_json TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(attempt_id, question_id),
        FOREIGN KEY(attempt_id) REFERENCES quiz_attempts(attempt_id),
        FOREIGN KEY(question_id) REFERENCES questions(question_id)
    )");
}

function ensure_schema_compat(PDO $pdo): void {
    $tables = [];
    foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table'") as $row) {
        $tables[$row['name']] = true;
    }

    $columns = function(string $table) use ($pdo, $tables): array {
        if (!isset($tables[$table])) return [];
        $cols = [];
        foreach ($pdo->query("PRAGMA table_info($table)") as $row) {
            $cols[$row['name']] = true;
        }
        return $cols;
    };

    $userCols = $columns('users');
    if (!isset($userCols['is_global_admin'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_global_admin INTEGER NOT NULL DEFAULT 0");
    }

    $uploadCols = $columns('uploads');
    if (!isset($uploadCols['storage_path'])) {
        $pdo->exec("ALTER TABLE uploads ADD COLUMN storage_path TEXT");
    }
    if (!isset($uploadCols['stored_name'])) {
        $pdo->exec("ALTER TABLE uploads ADD COLUMN stored_name TEXT DEFAULT ''");
    }
    if (!isset($uploadCols['mime_type'])) {
        $pdo->exec("ALTER TABLE uploads ADD COLUMN mime_type TEXT DEFAULT 'application/octet-stream'");
    }

    $subCols = $columns('submissions');
    if (!isset($subCols['course_id'])) {
        $pdo->exec("ALTER TABLE submissions ADD COLUMN course_id INTEGER");
    }
    if (!isset($subCols['content_text'])) {
        if (isset($subCols['content'])) {
            $pdo->exec("ALTER TABLE submissions ADD COLUMN content_text TEXT");
            $pdo->exec("UPDATE submissions SET content_text = COALESCE(content_text, content, '')");
        } else {
            $pdo->exec("ALTER TABLE submissions ADD COLUMN content_text TEXT DEFAULT ''");
        }
    }
    if (!isset($subCols['attachment_upload_id'])) {
        $pdo->exec("ALTER TABLE submissions ADD COLUMN attachment_upload_id INTEGER");
    }

    $answerCols = $columns('quiz_answers');
    if (!isset($answerCols['updated_at'])) {
        $pdo->exec("ALTER TABLE quiz_answers ADD COLUMN updated_at TEXT");
        $pdo->exec("UPDATE quiz_answers SET updated_at = COALESCE(updated_at, created_at, '' || strftime('%Y-%m-%dT%H:%M:%fZ','now') || '')");
    }
    if (!isset($answerCols['answer_id'])) {
        $pdo->exec("ALTER TABLE quiz_answers ADD COLUMN answer_id INTEGER");
        $pdo->exec("UPDATE quiz_answers SET answer_id = rowid WHERE answer_id IS NULL");
    }
}

function seed_admin(PDO $pdo): void {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $existing = $stmt->fetch();

    if ($existing) {
        return;
    }

    $username = 'admin';
    $password = getenv('INITIAL_ADMIN_PASSWORD');
    if ($password === false || $password === '') {
        $password = 'admin123';
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users(username, password_hash, display_name, created_at, is_global_admin) VALUES (?,?,?,?,1)");
    $stmt->execute([$username, $hash, 'Administrator', now_iso()]);
}