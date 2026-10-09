<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Schema
{
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = ON;');

        $statements = [
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                display_name TEXT NOT NULL,
                roles TEXT NOT NULL,
                affiliation TEXT DEFAULT "",
                bio TEXT DEFAULT "",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );',

            'CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                user_id INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at TEXT NOT NULL,
                ip_address TEXT DEFAULT "",
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                used INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS conference_phases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phase_key TEXT NOT NULL UNIQUE,
                label TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                status TEXT NOT NULL,
                description TEXT DEFAULT "",
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );',

            'CREATE TABLE IF NOT EXISTS paper_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                author_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                abstract TEXT NOT NULL,
                keywords TEXT DEFAULT "",
                topic TEXT DEFAULT "",
                pdf_path TEXT NOT NULL,
                supplementary_paths TEXT DEFAULT "",
                status TEXT NOT NULL DEFAULT "submitted",
                submission_phase TEXT NOT NULL DEFAULT "submission",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS reviewer_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id INTEGER NOT NULL,
                reviewer_id INTEGER NOT NULL,
                assigned_by INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT "assigned",
                due_date TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (submission_id, reviewer_id),
                FOREIGN KEY (submission_id) REFERENCES paper_submissions(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS conflicts_of_interest (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id INTEGER NOT NULL,
                reviewer_id INTEGER NOT NULL,
                declared_by INTEGER NOT NULL,
                reason TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (submission_id, reviewer_id),
                FOREIGN KEY (submission_id) REFERENCES paper_submissions(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (declared_by) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                assignment_id INTEGER NOT NULL UNIQUE,
                reviewer_id INTEGER NOT NULL,
                submission_id INTEGER NOT NULL,
                score INTEGER NOT NULL,
                confidence INTEGER NOT NULL,
                comments_to_author TEXT NOT NULL,
                comments_to_chair TEXT NOT NULL,
                recommendation TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "submitted",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (assignment_id) REFERENCES reviewer_assignments(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (submission_id) REFERENCES paper_submissions(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS rebuttals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id INTEGER NOT NULL,
                author_id INTEGER NOT NULL,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (submission_id) REFERENCES paper_submissions(id) ON DELETE CASCADE,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS decisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id INTEGER NOT NULL UNIQUE,
                chair_id INTEGER NOT NULL,
                decision TEXT NOT NULL,
                summary TEXT NOT NULL,
                notification TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (submission_id) REFERENCES paper_submissions(id) ON DELETE CASCADE,
                FOREIGN KEY (chair_id) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                actor_id INTEGER,
                action TEXT NOT NULL,
                target_type TEXT NOT NULL,
                target_id TEXT NOT NULL,
                details TEXT DEFAULT "",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
            );',

            'CREATE TABLE IF NOT EXISTS stored_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id INTEGER NOT NULL,
                owner_id INTEGER NOT NULL,
                original_name TEXT NOT NULL,
                stored_path TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                kind TEXT NOT NULL DEFAULT "manuscript",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (submission_id) REFERENCES paper_submissions(id) ON DELETE CASCADE,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS bulk_export_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chair_id INTEGER NOT NULL,
                format TEXT NOT NULL,
                scope TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "completed",
                stored_path TEXT DEFAULT "",
                record_count INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (chair_id) REFERENCES users(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS frontend_error_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                code TEXT NOT NULL,
                severity TEXT NOT NULL,
                source TEXT NOT NULL,
                message TEXT NOT NULL,
                details TEXT DEFAULT "",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );',

            'CREATE TABLE IF NOT EXISTS manuscript_access_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                submission_id INTEGER NOT NULL,
                file_id INTEGER,
                action TEXT NOT NULL,
                ip_address TEXT DEFAULT "",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (submission_id) REFERENCES paper_submissions(id) ON DELETE CASCADE
            );',

            'CREATE TABLE IF NOT EXISTS double_blind_preferences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                view_mode TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (submission_id, user_id),
                FOREIGN KEY (submission_id) REFERENCES paper_submissions(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );',
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }
}
