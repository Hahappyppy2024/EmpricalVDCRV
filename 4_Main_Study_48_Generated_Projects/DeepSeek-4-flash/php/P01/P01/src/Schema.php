<?php

declare(strict_types=1);

namespace App;

/**
 * Defines and applies the SQLite schema. The schema covers every persistent
 * entity declared by the use cases (User, Session, AccountAccess, CourseDiscovery,
 * Enrollment, CourseMaterials, Announcements, DiscussionBoard, AssignmentSubmission,
 * QuizLifecycle, Grades, GradeExport, BulkCourseReport, AdministrativeApi,
 * FrontendApiIntegration, ErrorResponses) plus the realtime event queue and
 * audit trail.
 */
final class Schema
{
    /** @return list<string> */
    public static function statements(): array
    {
        return [
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                label TEXT NOT NULL
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                display_name TEXT NOT NULL,
                role_id INTEGER NOT NULL REFERENCES roles(id),
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                token_hash TEXT UNIQUE NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                expires_at TEXT NOT NULL,
                ip_address TEXT NOT NULL DEFAULT ''
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id),
                token_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                used_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS account_access_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                detail_json TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS courses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT 'General',
                semester TEXT NOT NULL DEFAULT 'Fall 2026',
                instructor_id INTEGER,
                description TEXT NOT NULL DEFAULT '',
                visibility TEXT NOT NULL DEFAULT 'public',
                status TEXT NOT NULL DEFAULT 'open',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS enrollments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL REFERENCES courses(id),
                user_id INTEGER NOT NULL REFERENCES users(id),
                status TEXT NOT NULL DEFAULT 'enrolled',
                enrolled_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE (course_id, user_id)
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL REFERENCES courses(id),
                uploader_id INTEGER NOT NULL REFERENCES users(id),
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                filename TEXT NOT NULL,
                stored_path TEXT NOT NULL,
                mime_type TEXT NOT NULL DEFAULT 'application/octet-stream',
                size_bytes INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS announcements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL REFERENCES courses(id),
                instructor_id INTEGER NOT NULL REFERENCES users(id),
                title TEXT NOT NULL,
                body TEXT NOT NULL DEFAULT '',
                published_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS discussions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL REFERENCES courses(id),
                author_id INTEGER NOT NULL REFERENCES users(id),
                parent_id INTEGER REFERENCES discussions(id),
                subject TEXT NOT NULL,
                body TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL REFERENCES courses(id),
                instructor_id INTEGER NOT NULL REFERENCES users(id),
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                due_at TEXT,
                max_points INTEGER NOT NULL DEFAULT 100,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                assignment_id INTEGER NOT NULL REFERENCES assignments(id),
                student_id INTEGER NOT NULL REFERENCES users(id),
                filename TEXT NOT NULL,
                stored_path TEXT NOT NULL,
                mime_type TEXT NOT NULL DEFAULT 'application/octet-stream',
                size_bytes INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'submitted',
                submitted_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE (assignment_id, student_id)
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS quizzes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL REFERENCES courses(id),
                instructor_id INTEGER NOT NULL REFERENCES users(id),
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                time_limit_minutes INTEGER NOT NULL DEFAULT 10,
                published INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS quiz_questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quiz_id INTEGER NOT NULL REFERENCES quizzes(id),
                prompt TEXT NOT NULL,
                question_type TEXT NOT NULL DEFAULT 'multiple_choice',
                options_json TEXT NOT NULL DEFAULT '[]',
                correct_answer TEXT NOT NULL DEFAULT '',
                points INTEGER NOT NULL DEFAULT 1
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS quiz_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quiz_id INTEGER NOT NULL REFERENCES quizzes(id),
                student_id INTEGER NOT NULL REFERENCES users(id),
                status TEXT NOT NULL DEFAULT 'in_progress',
                score REAL NOT NULL DEFAULT 0,
                started_at TEXT NOT NULL DEFAULT (datetime('now')),
                submitted_at TEXT
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS quiz_answers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                attempt_id INTEGER NOT NULL REFERENCES quiz_attempts(id),
                question_id INTEGER NOT NULL REFERENCES quiz_questions(id),
                answer_text TEXT NOT NULL DEFAULT '',
                is_correct INTEGER NOT NULL DEFAULT 0,
                points_earned REAL NOT NULL DEFAULT 0
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS grade_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL REFERENCES courses(id),
                instructor_id INTEGER NOT NULL REFERENCES users(id),
                name TEXT NOT NULL,
                max_points REAL NOT NULL DEFAULT 100,
                weight REAL NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS grades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                grade_item_id INTEGER NOT NULL REFERENCES grade_items(id),
                student_id INTEGER NOT NULL REFERENCES users(id),
                score REAL,
                feedback TEXT NOT NULL DEFAULT '',
                graded_by INTEGER,
                graded_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE (grade_item_id, student_id)
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS exports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER,
                requested_by INTEGER NOT NULL REFERENCES users(id),
                format TEXT NOT NULL,
                kind TEXT NOT NULL DEFAULT 'grades',
                file_path TEXT,
                status TEXT NOT NULL DEFAULT 'ready',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                requested_by INTEGER NOT NULL REFERENCES users(id),
                report_type TEXT NOT NULL DEFAULT 'courses',
                filters_json TEXT NOT NULL DEFAULT '{}',
                summary_json TEXT NOT NULL DEFAULT '{}',
                status TEXT NOT NULL DEFAULT 'ready',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                entity_type TEXT NOT NULL DEFAULT '',
                entity_id INTEGER,
                detail_json TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL DEFAULT '',
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS realtime_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel TEXT NOT NULL,
                payload_json TEXT NOT NULL DEFAULT '{}',
                consumed INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
            SQL,
        ];
    }

    public static function apply(Database $db): void
    {
        foreach (self::statements() as $sql) {
            $db->execute($sql);
        }
    }
}
