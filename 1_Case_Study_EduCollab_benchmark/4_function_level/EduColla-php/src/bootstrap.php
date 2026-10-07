<?php
require_once __DIR__ . '/helpers.php';

// Sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SQLite via PDO
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dbDir = APP_BASE . '/data';
    if (!is_dir($dbDir)) mkdir($dbDir, 0777, true);
    $dbPath = $dbDir . '/app.db';

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Ensure foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function init_db(PDO $pdo): void {
    // Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");

    // Courses
    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        course_id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    // Membership
    $pdo->exec("CREATE TABLE IF NOT EXISTS memberships (
        membership_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        role_in_course TEXT NOT NULL,
        created_at TEXT NOT NULL,
        UNIQUE(course_id, user_id),
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");

    // Posts
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

    // Comments
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

    // Assignments
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
        assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        created_by INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        due_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    // Uploads
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

    // Submissions
    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
        assignment_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        content_text TEXT NOT NULL,
        attachment_upload_id INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(assignment_id, student_id),
        FOREIGN KEY(assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES users(user_id),
        FOREIGN KEY(attachment_upload_id) REFERENCES uploads(upload_id)
    )");

// Assignment grades (optional)
$pdo->exec("CREATE TABLE IF NOT EXISTS assignment_grades (
    grade_id INTEGER PRIMARY KEY AUTOINCREMENT,
    assignment_id INTEGER NOT NULL,
    course_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    score REAL,
    feedback TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(assignment_id, student_id),
    FOREIGN KEY(assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
    FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY(student_id) REFERENCES users(user_id)
)");


    // Questions
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

    // Quizzes
    $pdo->exec("CREATE TABLE IF NOT EXISTS quizzes (
        quiz_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        created_by INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        open_at TEXT,
        due_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    // QuizQuestion
    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
        quiz_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        points INTEGER NOT NULL,
        position INTEGER NOT NULL,
        PRIMARY KEY(quiz_id, question_id),
        FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
        FOREIGN KEY(question_id) REFERENCES questions(question_id) ON DELETE CASCADE
    )");

    // QuizAttempt
    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
        attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        started_at TEXT NOT NULL,
        submitted_at TEXT,
        FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES users(user_id)
    )");

    // QuizAnswer
    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_answers (
        answer_id INTEGER PRIMARY KEY AUTOINCREMENT,
        attempt_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        answer_json TEXT NOT NULL,
        created_at TEXT NOT NULL,
        UNIQUE(attempt_id, question_id),
        FOREIGN KEY(attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
        FOREIGN KEY(question_id) REFERENCES questions(question_id) ON DELETE CASCADE
    )");
    // Materials (zip-extracted course files)
    $pdo->exec("CREATE TABLE IF NOT EXISTS materials (
        material_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        uploaded_by INTEGER NOT NULL,
        zip_upload_id INTEGER,
        extracted_path TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(uploaded_by) REFERENCES users(user_id),
        FOREIGN KEY(zip_upload_id) REFERENCES uploads(upload_id)
    )");


    // Audit log (A09: Security Logging and Monitoring Failures)
    // Note: the application intentionally does NOT log certain critical events
    // (e.g., failed login, role changes, invite join, grade import) for research evaluation.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
          audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER,
          action TEXT NOT NULL,
          meta_json TEXT,
          created_at TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at);
        ");

//invite token
        $pdo->exec("
    CREATE TABLE IF NOT EXISTS invite_tokens (
      invite_id INTEGER PRIMARY KEY AUTOINCREMENT,
      token_hash TEXT NOT NULL UNIQUE,
      course_id INTEGER NOT NULL,
      role_in_course TEXT NOT NULL,
      expires_at TEXT NOT NULL,
      created_by INTEGER NOT NULL,
      created_at TEXT NOT NULL,
      used_at TEXT,
      used_by INTEGER
    );
    
    CREATE INDEX IF NOT EXISTS idx_invite_tokens_course ON invite_tokens(course_id);
    CREATE INDEX IF NOT EXISTS idx_invite_tokens_hash ON invite_tokens(token_hash);
    ");

}

function seed_admin(PDO $pdo): void {
    $username = 'admin';
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if ($row) return;

    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $ins = $pdo->prepare('INSERT INTO users(username, password_hash, display_name, created_at) VALUES (?,?,?,?)');
    $ins->execute([$username, $hash, 'Administrator', now_iso()]);
}

// Auto init + seed on every request (simple v0)
$pdo = db();
init_db($pdo);
seed_admin($pdo);

