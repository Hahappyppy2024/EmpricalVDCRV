import sqlite3
from database import get_db_connection
from werkzeug.security import generate_password_hash, check_password_hash

class UserRepository:
    @staticmethod
    def create(username, password, display_name):
        conn = get_db_connection()
        try:
            cursor = conn.execute(
                'INSERT INTO user (username, password_hash, display_name) VALUES (?, ?, ?)',
                (username, generate_password_hash(password), display_name)
            )
            conn.commit()
            return cursor.lastrowid
        except sqlite3.IntegrityError:
            return None
        finally:
            conn.close()

    @staticmethod
    def get_by_username(username):
        conn = get_db_connection()
        user = conn.execute('SELECT * FROM user WHERE username = ?', (username,)).fetchone()
        conn.close()
        return user

    @staticmethod
    def get_by_id(user_id):
        conn = get_db_connection()
        user = conn.execute('SELECT * FROM user WHERE user_id = ?', (user_id,)).fetchone()
        conn.close()
        return user

    @staticmethod
    def list_all():
        conn = get_db_connection()
        users = conn.execute('SELECT * FROM user ORDER BY created_at DESC').fetchall()
        conn.close()
        return users

    @staticmethod
    def verify_password(user, password):
        if not user: return False
        return check_password_hash(user['password_hash'], password)
