<?php
define('APP_BASE', dirname(__DIR__));
require_once APP_BASE . '/src/helpers.php';

// Sessions
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
        'path' => '/',
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dbFile = APP_BASE . '/data/app.db';
    if (!is_dir(dirname($dbFile))) mkdir(dirname($dbFile), 0777, true);
    $needInit = !file_exists($dbFile);
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if ($needInit) {
        init_db($pdo);
    } else {
        ensure_schema_compat($pdo);
    }
    seed_admin($pdo);
    return $pdo;
}

function init_db(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      user_id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      display_name TEXT NOT NULL,
      is_global_admin INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS courses (
      course_id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      description TEXT NOT NULL DEFAULT '',
      created_by INTEGER NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      FOREIGN KEY(created_by) REFERENCES users(user_id)
    );

    CREATE TABLE IF NOT EXISTS memberships (
      membership_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      user_id INTEGER NOT NULL,
      role_in_course TEXT NOT NULL,
      created_at TEXT NOT NULL,
      UNIQUE(course_id,user_id),
      FOREIGN KEY(course_id) REFERENCES courses(course_id),
      FOREIGN KEY(user_id) REFERENCES users(user_id)
    );

    CREATE TABLE IF NOT EXISTS posts (
      post_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      author_id INTEGER NOT NULL,
      title TEXT NOT NULL,
      body TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      FOREIGN KEY(course_id) REFERENCES courses(course_id),
      FOREIGN KEY(author_id) REFERENCES users(user_id)
    );

    CREATE TABLE IF NOT EXISTS comments (
      comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
      post_id INTEGER NOT NULL,
      course_id INTEGER NOT NULL,
      author_id INTEGER NOT NULL,
      body TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      FOREIGN KEY(post_id) REFERENCES posts(post_id),
      FOREIGN KEY(course_id) REFERENCES courses(course_id),
      FOREIGN KEY(author_id) REFERENCES users(user_id)
    );

    CREATE TABLE IF NOT EXISTS assignments (
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
    );

    CREATE TABLE IF NOT EXISTS submissions (
      submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
      assignment_id INTEGER NOT NULL,
      course_id INTEGER NOT NULL,
      student_id INTEGER NOT NULL,
      content TEXT NOT NULL DEFAULT '',
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      UNIQUE(assignment_id, student_id),
      FOREIGN KEY(assignment_id) REFERENCES assignments(assignment_id),
      FOREIGN KEY(student_id) REFERENCES users(user_id)
    );

    CREATE TABLE IF NOT EXISTS uploads (
      upload_id INTEGER PRIMARY KEY AUTOINCREMENT,
      course_id INTEGER NOT NULL,
      uploaded_by INTEGER NOT NULL,
      original_name TEXT NOT NULL,
      stored_name TEXT NOT NULL,
      storage_path TEXT,
      mime_type TEXT NOT NULL DEFAULT 'application/octet-stream',
      created_at TEXT NOT NULL,
      FOREIGN KEY(course_id) REFERENCES courses(course_id),
      FOREIGN KEY(uploaded_by) REFERENCES users(user_id)
    );

    CREATE TABLE IF NOT EXISTS questions (
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
    );

    CREATE TABLE IF NOT EXISTS quizzes (
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
    );

    CREATE TABLE IF NOT EXISTS quiz_questions (
      quiz_id INTEGER NOT NULL,
      question_id INTEGER NOT NULL,
      points INTEGER NOT NULL DEFAULT 1,
      position INTEGER NOT NULL DEFAULT 0,
      PRIMARY KEY (quiz_id, question_id),
      FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id),
      FOREIGN KEY(question_id) REFERENCES questions(question_id)
    );

    CREATE TABLE IF NOT EXISTS quiz_attempts (
      attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
      quiz_id INTEGER NOT NULL,
      student_id INTEGER NOT NULL,
      started_at TEXT NOT NULL,
      submitted_at TEXT,
      score REAL,
      UNIQUE(quiz_id, student_id),
      FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id),
      FOREIGN KEY(student_id) REFERENCES users(user_id)
    );

    CREATE TABLE IF NOT EXISTS quiz_answers (
      attempt_id INTEGER NOT NULL,
      question_id INTEGER NOT NULL,
      answer_json TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      PRIMARY KEY (attempt_id, question_id),
      FOREIGN KEY(attempt_id) REFERENCES quiz_attempts(attempt_id),
      FOREIGN KEY(question_id) REFERENCES questions(question_id)
    );
    ");
    ensure_schema_compat($pdo);
}

function ensure_schema_compat(PDO $pdo): void {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_global_admin INTEGER NOT NULL DEFAULT 0");
    $pdo->exec("ALTER TABLE uploads ADD COLUMN storage_path TEXT");
    $pdo->exec("ALTER TABLE submissions ADD COLUMN course_id INTEGER");
    $pdo->exec("ALTER TABLE quiz_answers ADD COLUMN updated_at TEXT");
}

function seed_admin(PDO $pdo): void {
    $stmt = $pdo->prepare('SELECT user_id, password_hash, is_global_admin FROM users WHERE username = ?');
    $stmt->execute(['admin']);
    $existing = $stmt->fetch();

    if ($existing) {
        if ((int)($existing['is_global_admin'] ?? 0) !== 1) {
            $upd = $pdo->prepare('UPDATE users SET is_global_admin=1 WHERE user_id=?');
            $upd->execute([(int)$existing['user_id']]);
        }
        return;
    }

    $username = 'admin';
    $password = getenv('INITIAL_ADMIN_PASSWORD');
    if ($password === false || $password === '') {
        $password = 'admin123';
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users(username, password_hash, display_name, created_at, is_global_admin) VALUES (?,?,?,?,1)');
    $ins->execute([$username, $hash, 'Administrator', now_iso()]);
}