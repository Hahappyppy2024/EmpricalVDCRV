
import shutil
import socket
import subprocess
import time
from pathlib import Path

import pytest
import requests
import sqlite3


@pytest.fixture(scope="session")
def source_app_dir() -> Path:
    return Path(__file__).resolve().parents[2]


@pytest.fixture()
def app_instance(tmp_path, source_app_dir):
    app_dir = tmp_path / "educollab_php_v0"
    shutil.copytree(source_app_dir, app_dir)

    db_path = app_dir / "data" / "app.db"
    if db_path.exists():
        db_path.unlink()

    storage_dir = app_dir / "storage"
    if storage_dir.exists():
        shutil.rmtree(storage_dir)

    host = "127.0.0.1"
    sock = socket.socket()
    sock.bind((host, 0))
    port = sock.getsockname()[1]
    sock.close()

    proc = subprocess.Popen(
        ["php", "-S", f"{host}:{port}", "-t", "public"],
        cwd=str(app_dir),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    base_url = f"http://{host}:{port}"

    deadline = time.time() + 10
    while time.time() < deadline:
        try:
            r = requests.get(base_url + "/login", timeout=0.5)
            if r.status_code in (200, 302):
                break
        except Exception:
            time.sleep(0.1)
    else:
        proc.kill()
        raise RuntimeError("PHP server did not start")

    try:
        yield {
            "app_dir": app_dir,
            "base_url": base_url,
            "db_path": app_dir / "data" / "app.db",
        }
    finally:
        proc.kill()
        try:
            proc.wait(timeout=3)
        except Exception:
            pass


@pytest.fixture()
def client(app_instance):
    session = requests.Session()
    session.headers.update({"Accept": "application/json"})
    return session, app_instance["base_url"]


def open_db(db_path):
    conn = sqlite3.connect(str(db_path))
    conn.row_factory = sqlite3.Row
    return conn
