<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__) . '/repositories/UserRepository.php';
require_once dirname(__DIR__) . '/repositories/CourseRepository.php';
require_once dirname(__DIR__) . '/repositories/MembershipRepository.php';
require_once dirname(__DIR__) . '/repositories/PostRepository.php';
require_once dirname(__DIR__) . '/repositories/CommentRepository.php';
require_once dirname(__DIR__) . '/repositories/AssignmentRepository.php';
require_once dirname(__DIR__) . '/repositories/SubmissionRepository.php';
require_once dirname(__DIR__) . '/repositories/UploadRepository.php';
require_once dirname(__DIR__) . '/repositories/QuestionRepository.php';
require_once dirname(__DIR__) . '/repositories/QuizRepository.php';
require_once dirname(__DIR__) . '/repositories/QuizAttemptRepository.php';

function init_db(array $config): void
{
    $pdo = Database::connection($config);

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        course_id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS memberships (
        membership_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        role_in_course TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(user_id),
        UNIQUE(course_id, user_id)
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
        due_at TEXT NULL,
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



    $pdo->exec("CREATE TABLE IF NOT EXISTS questions (
        question_id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL,
        created_by INTEGER NOT NULL,
        qtype TEXT NOT NULL,
        prompt TEXT NOT NULL,
        options_json TEXT NULL,
        answer_json TEXT NULL,
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
        open_at TEXT NULL,
        due_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(created_by) REFERENCES users(user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
        quiz_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        points INTEGER NOT NULL DEFAULT 0,
        position INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (quiz_id, question_id),
        FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
        FOREIGN KEY(question_id) REFERENCES questions(question_id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
        attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        started_at TEXT NOT NULL,
        submitted_at TEXT NULL,
        FOREIGN KEY(quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES users(user_id)
    )");

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
        assignment_id INTEGER NOT NULL,
        course_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        content_text TEXT NOT NULL DEFAULT '',
        attachment_upload_id INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
        FOREIGN KEY(course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES users(user_id),
        FOREIGN KEY(attachment_upload_id) REFERENCES uploads(upload_id)
    )");
}

function seed_admin(array $config): void
{
    $userRepo = new UserRepository(Database::connection($config));
    $admin = $userRepo->findByUsername($config['admin']['username']);
    if ($admin) {
        return;
    }

    $userRepo->create([
        'username' => $config['admin']['username'],
        'password_hash' => password_hash($config['admin']['password'], PASSWORD_DEFAULT),
        'display_name' => $config['admin']['display_name'],
    ]);
}

function backfill_course_creator_memberships(array $config): void
{
    $pdo = Database::connection($config);
    $membershipRepo = new MembershipRepository($pdo);
    $stmt = $pdo->query('SELECT course_id, created_by FROM courses');
    foreach ($stmt->fetchAll() as $course) {
        $membershipRepo->ensureMembership((int) $course['course_id'], (int) $course['created_by'], 'teacher');
    }
}
