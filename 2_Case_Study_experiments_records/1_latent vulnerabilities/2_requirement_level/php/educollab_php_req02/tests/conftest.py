
import shutil
import socket
import subprocess
import time
from pathlib import Path

import pytest
import requests


@pytest.fixture(scope="session")
def source_app_dir() -> Path:
    return Path(__file__).resolve().parent.parent


@pytest.fixture()
def app_instance(tmp_path, source_app_dir):
    app_dir = tmp_path / "app"
    shutil.copytree(source_app_dir, app_dir, ignore=shutil.ignore_patterns('tests', '.git', '__pycache__', '*.pyc', '.pytest_cache'))

    db_path = app_dir / 'data' / 'app.db'
    db_path.parent.mkdir(parents=True, exist_ok=True)
    if db_path.exists():
        db_path.unlink()

    host = '127.0.0.1'
    sock = socket.socket()
    sock.bind((host, 0))
    port = sock.getsockname()[1]
    sock.close()

    proc = subprocess.Popen(
        ['php', '-S', f'{host}:{port}', '-t', 'public'],
        cwd=str(app_dir),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    base_url = f'http://{host}:{port}'
    deadline = time.time() + 10
    last_error = None
    while time.time() < deadline:
        try:
            r = requests.get(base_url + '/', timeout=0.5)
            if r.status_code in (200, 404):
                break
        except Exception as exc:
            last_error = exc
        time.sleep(0.2)
    else:
        proc.terminate()
        raise RuntimeError(f'PHP server did not start: {last_error}')

    yield {'app_dir': app_dir, 'base_url': base_url, 'proc': proc}

    proc.terminate()
    try:
        proc.wait(timeout=5)
    except subprocess.TimeoutExpired:
        proc.kill()
