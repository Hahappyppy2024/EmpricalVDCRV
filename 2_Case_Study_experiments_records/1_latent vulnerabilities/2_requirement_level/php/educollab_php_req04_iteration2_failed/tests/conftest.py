
import os
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
    shutil.copytree(source_app_dir, app_dir, ignore=shutil.ignore_patterns('tests', '.git', '__pycache__', '.pytest_cache', '*.pyc'))

    db_path = app_dir / 'data' / 'app.db'
    if db_path.exists():
        db_path.unlink()

    storage_dir = app_dir / 'storage'
    if storage_dir.exists():
        shutil.rmtree(storage_dir)

    host = '127.0.0.1'
    sock = socket.socket()
    sock.bind((host, 0))
    port = sock.getsockname()[1]
    sock.close()

    env = os.environ.copy()
    proc = subprocess.Popen(
        ['php', '-S', f'{host}:{port}', '-t', 'public'],
        cwd=str(app_dir),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        env=env,
    )

    base_url = f'http://{host}:{port}'
    session = requests.Session()

    for _ in range(50):
        try:
            r = session.get(base_url + '/', timeout=0.5)
            if r.status_code in (200, 404):
                break
        except Exception:
            time.sleep(0.1)
    else:
        proc.kill()
        raise RuntimeError('PHP server did not start in time')

    yield {'app_dir': app_dir, 'base_url': base_url, 'session': session, 'proc': proc}

    try:
        session.close()
    finally:
        proc.terminate()
        try:
            proc.wait(timeout=3)
        except subprocess.TimeoutExpired:
            proc.kill()
