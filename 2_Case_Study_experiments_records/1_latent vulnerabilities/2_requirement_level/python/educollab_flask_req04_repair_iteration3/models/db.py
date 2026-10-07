import os
import sqlite3

from flask import current_app, g
from werkzeug.security import generate_password_hash


SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS courses (
    course_id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT DEFAULT '',
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS memberships (
    membership_id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    role_in_course TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(course_id, user_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS assignments (
    assignment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    created_by INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT DEFAULT '',
    due_at TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS uploads (
    upload_id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    uploaded_by INTEGER NOT NULL,
    original_name TEXT NOT NULL,
    storage_path TEXT NOT NULL,
    is_student_visible INTEGER NOT NULL DEFAULT 0,
    allow_submission_attachment INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS submissions (
    submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
    assignment_id INTEGER NOT NULL,
    course_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    content_text TEXT DEFAULT '',
    attachment_upload_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (attachment_upload_id) REFERENCES uploads(upload_id)
);
"""


def get_db():
    if 'db' not in g:
        db_path = current_app.config['DATABASE']
        os.makedirs(os.path.dirname(db_path), exist_ok=True)
        g.db = sqlite3.connect(db_path)
        g.db.row_factory = sqlite3.Row
    return g.db


def close_db(_error=None):
    db = g.pop('db', None)
    if db is not None:
        db.close()


def init_db(app):
    with app.app_context():
        db = get_db()
        db.executescript(SCHEMA_SQL)
        columns = {row['name'] for row in db.execute("PRAGMA table_info(uploads)").fetchall()}
        if 'is_student_visible' not in columns:
            db.execute('ALTER TABLE uploads ADD COLUMN is_student_visible INTEGER NOT NULL DEFAULT 0')
        if 'allow_submission_attachment' not in columns:
            db.execute('ALTER TABLE uploads ADD COLUMN allow_submission_attachment INTEGER NOT NULL DEFAULT 0')
        db.commit()
        upload_root = app.config.get('UPLOAD_ROOT', 'storage/uploads')
        os.makedirs(upload_root, exist_ok=True)
    app.teardown_appcontext(close_db)


def seed_admin(app):
    with app.app_context():
        db = get_db()
        existing = db.execute('SELECT user_id FROM users WHERE username = ?', ('admin',)).fetchone()
        if existing is None:
            db.execute(
                'INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
                ('admin', generate_password_hash('admin123'), 'Default Admin')
            )
            db.commit()