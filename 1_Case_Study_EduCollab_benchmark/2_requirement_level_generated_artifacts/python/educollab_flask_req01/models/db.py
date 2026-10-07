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
        db.commit()
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
