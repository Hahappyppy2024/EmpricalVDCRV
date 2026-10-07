
from pathlib import Path
import sqlite3


def api_url(app, path: str) -> str:
    return app['base_url'] + path


def json_of(resp):
    try:
        return resp.json()
    except Exception:
        return {}


def register(session, app, username: str, password: str = 'pass123', display_name: str | None = None):
    if display_name is None:
        display_name = username
    resp = session.post(api_url(app, '/api/auth/register'), json={
        'username': username,
        'password': password,
        'display_name': display_name,
    }, timeout=5)
    return resp


def login(session, app, username: str, password: str = 'pass123'):
    return session.post(api_url(app, '/api/auth/login'), json={
        'username': username,
        'password': password,
    }, timeout=5)


def logout(session, app):
    return session.post(api_url(app, '/api/auth/logout'), timeout=5)


def create_course(session, app, title='Course A', description='Desc'):
    return session.post(api_url(app, '/api/courses'), json={
        'title': title,
        'description': description,
    }, timeout=5)


def add_member(session, app, course_id: int, user_id: int, role='student'):
    return session.post(api_url(app, f'/api/courses/{course_id}/members'), json={
        'user_id': user_id,
        'role_in_course': role,
    }, timeout=5)


def create_assignment(session, app, course_id: int, title='HW1', description='Do it', due_at='2030-01-01T00:00:00Z'):
    return session.post(api_url(app, f'/api/courses/{course_id}/assignments'), json={
        'title': title,
        'description': description,
        'due_at': due_at,
    }, timeout=5)


def upload_file(session, app, course_id: int, filename='note.txt', content=b'hello', ctype='text/plain'):
    files = {'file': (filename, content, ctype)}
    return session.post(api_url(app, f'/api/courses/{course_id}/uploads'), files=files, timeout=5)


def get_user_id(app, username: str):
    db_path = Path(app['app_dir']) / 'data' / 'app.db'
    con = sqlite3.connect(db_path)
    try:
        row = con.execute('SELECT user_id FROM users WHERE username=?', (username,)).fetchone()
        return None if row is None else int(row[0])
    finally:
        con.close()
