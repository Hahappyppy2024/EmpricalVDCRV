import shutil
import socket
import subprocess
import time
from pathlib import Path

import pytest
import requests


@pytest.fixture(scope='session')
def source_app_dir() -> Path:
    return Path(__file__).resolve().parent.parent


@pytest.fixture()
def app_instance(tmp_path: Path, source_app_dir: Path):
    app_root = tmp_path / 'app'
    shutil.copytree(
        source_app_dir,
        app_root,
        ignore=shutil.ignore_patterns('.venv', 'venv', '__pycache__', '.pytest_cache', 'tests'),
    )

    data_dir = app_root / 'data'
    data_dir.mkdir(parents=True, exist_ok=True)
    db_path = data_dir / 'app.db'
    if db_path.exists():
        db_path.unlink()

    host = '127.0.0.1'
    sock = socket.socket()
    sock.bind((host, 0))
    port = sock.getsockname()[1]
    sock.close()

    proc = subprocess.Popen(
        ['php', '-S', f'{host}:{port}', '-t', 'public'],
        cwd=str(app_root),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    base_url = f'http://{host}:{port}'
    deadline = time.time() + 10
    last_error = None
    while time.time() < deadline:
        try:
            r = requests.get(base_url + '/', timeout=1)
            if r.status_code in (200, 404, 500):
                break
        except Exception as exc:  # pragma: no cover
            last_error = exc
            time.sleep(0.2)
    else:  # pragma: no cover
        proc.terminate()
        raise RuntimeError(f'PHP server did not start: {last_error}')

    yield {
        'app_root': app_root,
        'base_url': base_url,
        'proc': proc,
        'db_path': db_path,
    }

    proc.terminate()
    try:
        proc.wait(timeout=5)
    except subprocess.TimeoutExpired:  # pragma: no cover
        proc.kill()
        proc.wait(timeout=5)


@pytest.fixture()
def client(app_instance):
    s = requests.Session()
    s.base_url = app_instance['base_url']
    return s


def api_url(client: requests.Session, path: str) -> str:
    return client.base_url + path


def api_post(client: requests.Session, path: str, json: dict, expected=(200, 201)):
    res = client.post(api_url(client, path), json=json, timeout=5)
    assert res.status_code in expected, (res.status_code, res.text)
    return res


def api_put(client: requests.Session, path: str, json: dict, expected=(200,)):
    res = client.put(api_url(client, path), json=json, timeout=5)
    assert res.status_code in expected, (res.status_code, res.text)
    return res


def api_delete(client: requests.Session, path: str, expected=(200,)):
    res = client.delete(api_url(client, path), timeout=5)
    assert res.status_code in expected, (res.status_code, res.text)
    return res


def register(client: requests.Session, username: str, password: str = 'pass123', display_name: str | None = None):
    if display_name is None:
        display_name = username.title()
    res = api_post(
        client,
        '/api/auth/register',
        {'username': username, 'password': password, 'display_name': display_name},
        expected=(201,),
    )
    payload = res.json()
    assert payload['success'] is True
    return payload['user']


def login(client: requests.Session, username: str, password: str = 'pass123'):
    res = api_post(
        client,
        '/api/auth/login',
        {'username': username, 'password': password},
        expected=(200,),
    )
    payload = res.json()
    assert payload['success'] is True
    return payload['user']


def logout(client: requests.Session):
    res = client.post(api_url(client, '/api/auth/logout'), timeout=5)
    assert res.status_code == 200, (res.status_code, res.text)
    return res.json()


def me(client: requests.Session, expected_status: int = 200):
    res = client.get(api_url(client, '/api/auth/me'), timeout=5)
    assert res.status_code == expected_status, (res.status_code, res.text)
    return res


def create_course(client: requests.Session, title: str, description: str = ''):
    res = api_post(
        client,
        '/api/courses',
        {'title': title, 'description': description},
        expected=(201,),
    )
    payload = res.json()
    assert payload['success'] is True
    return payload['course']