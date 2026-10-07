import os
import sqlite3
from flask import current_app
from werkzeug.security import generate_password_hash


def _get_db_path():
    """Read DB path from Flask config when possible; fallback to Config."""
    try:
        return current_app.config["DATABASE_PATH"]
    except Exception:
        from config import Config
        return Config.DATABASE_PATH


def get_db_connection():
    db_path = _get_db_path()

    # If using shared in-memory database, sqlite needs uri=True
    if db_path == "file::memory:?cache=shared":
        conn = sqlite3.connect(db_path, uri=True)
    else:
        conn = sqlite3.connect(db_path)

    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


def init_db():
    """
    Initialize database schema.
    - In TESTING mode: drop tables first to ensure a clean state.
    """
    try:
        is_testing = bool(current_app.config.get("TESTING", False))
    except Exception:
        is_testing = False

    conn = get_db_connection()
    cursor = conn.cursor()

    # ----------------------------
    # Drop tables for clean tests
    # ----------------------------
    if is_testing:
        # Drop children first to satisfy FK constraints
        drop_order = [
            "audit_log",
            "invite_token",
            "quiz_answer",
            "quiz_attempt",
            "quiz_question",
            "quiz",
            "question",
            "material_archive",
            "upload",
            "submission",
            "assignment",
            "comment",
            "post",
            "membership",
            "course",
            "user",
        ]
        for t in drop_order:
            cursor.execute(f"DROP TABLE IF EXISTS {t}")

    # ----------------------------
    # Core tables
    # ----------------------------
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS user (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            display_name TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS course (
            course_id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            created_by INTEGER REFERENCES user(user_id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS membership (
            membership_id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER REFERENCES course(course_id),
            user_id INTEGER REFERENCES user(user_id),
            role_in_course TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(course_id, user_id)
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS post (
            post_id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER REFERENCES course(course_id),
            author_id INTEGER REFERENCES user(user_id),
            title TEXT NOT NULL,
            body TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS comment (
            comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER REFERENCES post(post_id),
            course_id INTEGER REFERENCES course(course_id),
            author_id INTEGER REFERENCES user(user_id),
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS assignment (
            assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER REFERENCES course(course_id),
            created_by INTEGER REFERENCES user(user_id),
            title TEXT NOT NULL,
            description TEXT,
            due_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    # Single source of truth for submission schema (include "grade" and other columns up-front)
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS submission (
            submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
            assignment_id INTEGER REFERENCES assignment(assignment_id),
            course_id INTEGER REFERENCES course(course_id),
            student_id INTEGER REFERENCES user(user_id),
            content_text TEXT,
            attachment_upload_id INTEGER,
            grade REAL,
            file_path TEXT,
            extracted_path TEXT,
            score REAL,
            feedback TEXT,
            submit_time TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS upload (
            upload_id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER REFERENCES course(course_id),
            uploaded_by INTEGER REFERENCES user(user_id),
            original_name TEXT NOT NULL,
            storage_path TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS question (
            question_id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER REFERENCES course(course_id),
            created_by INTEGER REFERENCES user(user_id),
            qtype TEXT NOT NULL,
            prompt TEXT NOT NULL,
            options_json TEXT,
            answer_json TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS quiz (
            quiz_id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER REFERENCES course(course_id),
            created_by INTEGER REFERENCES user(user_id),
            title TEXT NOT NULL,
            description TEXT,
            open_at TIMESTAMP,
            due_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS quiz_question (
            quiz_id INTEGER REFERENCES quiz(quiz_id),
            question_id INTEGER REFERENCES question(question_id),
            points INTEGER DEFAULT 1,
            position INTEGER DEFAULT 0,
            PRIMARY KEY (quiz_id, question_id)
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS quiz_attempt (
            attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
            quiz_id INTEGER REFERENCES quiz(quiz_id),
            course_id INTEGER REFERENCES course(course_id),
            student_id INTEGER REFERENCES user(user_id),
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS quiz_answer (
            answer_id INTEGER PRIMARY KEY AUTOINCREMENT,
            attempt_id INTEGER REFERENCES quiz_attempt(attempt_id),
            question_id INTEGER REFERENCES question(question_id),
            answer_json TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS material_archive (
            archive_id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            uploaded_by INTEGER NOT NULL,
            original_name TEXT NOT NULL,
            storage_path TEXT NOT NULL,
            extracted_dir TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    # ----------------------------
    # Invite tokens (CRYPTO)
    # ----------------------------
    cursor.executescript(
        """
        -- Invite tokens: store hash only, enforce expiry and single-use
        CREATE TABLE IF NOT EXISTS invite_token (
          invite_token_id INTEGER PRIMARY KEY AUTOINCREMENT,
          course_id       INTEGER NOT NULL,
          role_in_course  TEXT    NOT NULL,
          token_hash      TEXT    NOT NULL UNIQUE,
          created_by      INTEGER NOT NULL,
          created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
          expires_at      TEXT    NOT NULL,
          used_at         TEXT,
          used_by         INTEGER,
          FOREIGN KEY(course_id) REFERENCES course(course_id),
          FOREIGN KEY(created_by) REFERENCES user(user_id),
          FOREIGN KEY(used_by) REFERENCES user(user_id)
        );

        CREATE INDEX IF NOT EXISTS idx_invite_token_course ON invite_token(course_id);
        CREATE INDEX IF NOT EXISTS idx_invite_token_hash  ON invite_token(token_hash);
        """
    )

    # ----------------------------
    # Audit log (A09)
    # ----------------------------
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS audit_log (
            audit_id INTEGER PRIMARY KEY AUTOINCREMENT,
            actor_user_id INTEGER,
            actor_username TEXT,
            action TEXT NOT NULL,
            target_type TEXT,
            target_id INTEGER,
            meta_json TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """
    )

    conn.commit()
    conn.close()


def seed_admin():
    conn = get_db_connection()
    admin = conn.execute("SELECT * FROM user WHERE username = ?", ("admin",)).fetchone()
    if not admin:
        print("Seeding admin user...")
        conn.execute(
            "INSERT INTO user (username, password_hash, display_name) VALUES (?, ?, ?)",
            ("admin", generate_password_hash("admin123"), "Administrator"),
        )
        conn.commit()
    conn.close()