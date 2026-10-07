from models.db import get_db


def create_user(username, password_hash, display_name):
    db = get_db()
    cursor = db.execute(
        'INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
        (username, password_hash, display_name),
    )
    db.commit()
    return get_user_by_id(cursor.lastrowid)


def get_user_by_username(username):
    db = get_db()
    return db.execute(
        'SELECT user_id, username, password_hash, display_name, created_at FROM users WHERE username = ?',
        (username,),
    ).fetchone()


def get_user_by_id(user_id):
    db = get_db()
    return db.execute(
        'SELECT user_id, username, password_hash, display_name, created_at FROM users WHERE user_id = ?',
        (user_id,),
    ).fetchone()


def list_users():
    db = get_db()
    return db.execute(
        'SELECT user_id, username, display_name, created_at FROM users ORDER BY user_id ASC'
    ).fetchall()
