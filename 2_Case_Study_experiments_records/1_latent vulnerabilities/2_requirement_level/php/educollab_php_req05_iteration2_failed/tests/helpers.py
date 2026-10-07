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
    return session.post(api_url(app, '/api/auth/register'), json={
        'username': username,
        'password': password,
        'display_name': display_name,
    }, timeout=5)


def login(session, app, username: str, password: str = 'pass123'):
    return session.post(api_url(app, '/api/auth/login'), json={
        'username': username,
        'password': password,
    }, timeout=5)


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


def get_user_id(app, username: str):
    db_path = Path(app['app_dir']) / 'data' / 'app.db'
    con = sqlite3.connect(db_path)
    try:
        row = con.execute('SELECT user_id FROM users WHERE username=?', (username,)).fetchone()
        return None if row is None else int(row[0])
    finally:
        con.close()


def create_question(session, app, course_id: int, *, qtype='single_choice', prompt='2+2=?', options=None, answer=None):
    if options is None:
        options = ['3', '4', '5']
    if answer is None:
        answer = {'correct': '4'}
    return session.post(api_url(app, f'/api/courses/{course_id}/questions'), json={
        'qtype': qtype,
        'prompt': prompt,
        'options_json': options,
        'answer_json': answer,
    }, timeout=5)


def create_quiz(session, app, course_id: int, *, title='Quiz 1', description='desc', open_at=None, due_at=None):
    payload = {'title': title, 'description': description}
    if open_at is not None:
        payload['open_at'] = open_at
    if due_at is not None:
        payload['due_at'] = due_at
    return session.post(api_url(app, f'/api/courses/{course_id}/quizzes'), json=payload, timeout=5)


def configure_quiz_question(session, app, course_id: int, quiz_id: int, question_id: int, *, points=5, position=1):
    return session.post(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}/questions'), json={
        'question_id': question_id,
        'points': points,
        'position': position,
    }, timeout=5)
