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
    ready = False
    for _ in range(80):
        try:
            r = requests.get(f"{base_url}/login", timeout=0.25)
            if r.status_code in (200, 302, 401, 403):
                ready = True
                break
        except Exception:
            pass
        time.sleep(0.1)

    if not ready:
        proc.terminate()
        try:
            proc.wait(timeout=3)
        except subprocess.TimeoutExpired:
            proc.kill()
        raise RuntimeError("PHP test server did not start correctly")

    yield {"app_dir": app_dir, "base_url": base_url}

    proc.terminate()
    try:
        proc.wait(timeout=3)
    except subprocess.TimeoutExpired:
        proc.kill()