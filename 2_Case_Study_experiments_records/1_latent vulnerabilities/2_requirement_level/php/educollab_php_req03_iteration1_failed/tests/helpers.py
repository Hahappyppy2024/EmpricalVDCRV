import sqlite3
from pathlib import Path


def api_url(base_url: str, path: str) -> str:
    return base_url.rstrip('/') + path


def _json_or_fail(res, endpoint: str):
    try:
        return res.json()
    except Exception as exc:
        raise AssertionError(
            f'Expected JSON from {endpoint}, got status={res.status_code}, body:\n{res.text}'
        ) from exc


def register(session, base_url, username, password='pass123', display_name=None):
    if display_name is None:
        display_name = username
    res = session.post(
        api_url(base_url, '/api/auth/register'),
        json={
            'username': username,
            'password': password,
            'display_name': display_name,
        },
        timeout=5,
    )
    assert res.status_code in (200, 201), res.text
    payload = _json_or_fail(res, '/api/auth/register')
    return payload['user']


def login(session, base_url, username, password='pass123'):
    res = session.post(
        api_url(base_url, '/api/auth/login'),
        json={'username': username, 'password': password},
        timeout=5,
    )
    assert res.status_code == 200, res.text
    payload = _json_or_fail(res, '/api/auth/login')
    return payload['user']


def logout(session, base_url):
    return session.post(api_url(base_url, '/api/auth/logout'), timeout=5)


def create_course(session, base_url, title='Course A', description='desc'):
    res = session.post(
        api_url(base_url, '/api/courses'),
        json={'title': title, 'description': description},
        timeout=5,
    )
    assert res.status_code in (200, 201), res.text
    payload = _json_or_fail(res, '/api/courses')
    return payload['course']


def add_member(session, base_url, course_id, user_id, role_in_course='student'):
    res = session.post(
        api_url(base_url, f'/api/courses/{course_id}/members'),
        json={'user_id': user_id, 'role_in_course': role_in_course},
        timeout=5,
    )
    assert res.status_code in (200, 201), res.text
    payload = _json_or_fail(res, f'/api/courses/{course_id}/members')
    return payload['membership']


def create_post(session, base_url, course_id, title='P1', body='body1'):
    res = session.post(
        api_url(base_url, f'/api/courses/{course_id}/posts'),
        json={'title': title, 'body': body},
        timeout=5,
    )
    assert res.status_code in (200, 201), res.text
    _json_or_fail(res, f'/api/courses/{course_id}/posts')
    return res


def create_comment(session, base_url, course_id, post_id, body='comment1'):
    res = session.post(
        api_url(base_url, f'/api/courses/{course_id}/posts/{post_id}/comments'),
        json={'body': body},
        timeout=5,
    )
    assert res.status_code in (200, 201), res.text
    _json_or_fail(res, f'/api/courses/{course_id}/posts/{post_id}/comments')
    return res


def get_user_id(app_dir: Path, username: str):
    db = sqlite3.connect(app_dir / 'data' / 'app.db')
    try:
        row = db.execute('SELECT user_id FROM users WHERE username=?', (username,)).fetchone()
        return row[0] if row else None
    finally:
        db.close()