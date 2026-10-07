import importlib
import sqlite3
import sys
from pathlib import Path

import pytest


PROJECT_ROOT = Path(__file__).resolve().parents[2]

if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))


def _load_app_module():
    candidates = [
        'app',
        'main',
        'src.app',
        'educollab_app',
        'application',
        'src.main',
    ]
    last_error = None
    for name in candidates:
        try:
            return importlib.import_module(name)
        except Exception as exc:  # pragma: no cover
            last_error = exc
    raise RuntimeError(f'Could not import application module from {candidates}: {last_error}')


def _force_init_db(app_module, app):
    init_db = getattr(app_module, 'init_db', None)
    seed_admin = getattr(app_module, 'seed_admin', None)

    if init_db is None or seed_admin is None:
        db_module = importlib.import_module('models.db')
        init_db = getattr(db_module, 'init_db')
        seed_admin = getattr(db_module, 'seed_admin')

    with app.app_context():
        try:
            init_db(app)
        except TypeError:
            init_db()

        try:
            seed_admin(app)
        except TypeError:
            seed_admin()


def build_app(tmp_path: Path):
    app_module = _load_app_module()
    db_path = tmp_path / 'data' / 'app.db'
    db_path.parent.mkdir(parents=True, exist_ok=True)

    if not hasattr(app_module, 'create_app'):
        raise RuntimeError('Expected a create_app() factory in app.py (or equivalent).')

    create_app = app_module.create_app

    try:
        app = create_app({
            'TESTING': True,
            'SECRET_KEY': 'test-secret',
            'DATABASE': str(db_path),
        })
    except TypeError:
        app = create_app()
        app.config.update(
            TESTING=True,
            SECRET_KEY='test-secret',
            DATABASE=str(db_path),
        )

    _force_init_db(app_module, app)

    return app, db_path


@pytest.fixture()
def app(tmp_path, monkeypatch):
    monkeypatch.chdir(PROJECT_ROOT)
    app, _db_path = build_app(tmp_path)
    return app


@pytest.fixture()
def client(app):
    return app.test_client()


@pytest.fixture()
def db_path(app):
    return Path(app.config['DATABASE'])


@pytest.fixture()
def fresh_client(app):
    def _make():
        return app.test_client()
    return _make


def api_register(client, username, password='pass123', display_name=None):
    return client.post(
        '/api/auth/register',
        json={
            'username': username,
            'password': password,
            'display_name': display_name or username,
        },
    )


def api_login(client, username, password='pass123'):
    return client.post('/api/auth/login', json={'username': username, 'password': password})


def api_logout(client):
    return client.post('/api/auth/logout')


def create_course(client, title='Course A', description='desc'):
    return client.post('/api/courses', json={'title': title, 'description': description})


def get_user_id(db_path: Path, username: str):
    conn = sqlite3.connect(db_path)
    try:
        row = conn.execute('SELECT user_id FROM users WHERE username = ?', (username,)).fetchone()
        return row[0] if row else None
    finally:
        conn.close()


def get_membership_id(db_path: Path, course_id: int, username: str):
    conn = sqlite3.connect(db_path)
    try:
        row = conn.execute(
            'SELECT m.membership_id '
            'FROM memberships m JOIN users u ON u.user_id = m.user_id '
            'WHERE m.course_id = ? AND u.username = ?',
            (course_id, username),
        ).fetchone()
        return row[0] if row else None
    finally:
        conn.close()