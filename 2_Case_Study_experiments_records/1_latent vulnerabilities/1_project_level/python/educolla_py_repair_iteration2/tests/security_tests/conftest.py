import os
import shutil
import socket
import subprocess
import sys
import time
from pathlib import Path

import pytest
import requests


def _detect_source_app_dir() -> Path:
    here = Path(__file__).resolve()
    candidates = [
        here.parent.parent.parent,
        here.parent.parent,
        here.parent,
        Path.cwd(),
    ]
    for candidate in candidates:
        if (candidate / "app.py").exists() and (candidate / "repos").exists():
            return candidate
    raise FileNotFoundError(
        "Could not locate the Flask app directory automatically. "
        "Place these tests under the project tree so that app.py and repos/ are reachable."
    )


@pytest.fixture(scope="session")
def source_app_dir() -> Path:
    return _detect_source_app_dir()


@pytest.fixture()
def app_instance(tmp_path, source_app_dir):
    app_dir = tmp_path / "educollab_flask_mvc_v0"
    shutil.copytree(source_app_dir, app_dir)

    # reset DB/uploads for each test
    db_path = app_dir / "data" / "app.db"
    if db_path.exists():
        db_path.unlink()

    uploads_dir = app_dir / "data" / "uploads"
    if uploads_dir.exists():
        shutil.rmtree(uploads_dir)

    host = "127.0.0.1"
    sock = socket.socket()
    sock.bind((host, 0))
    port = sock.getsockname()[1]
    sock.close()

    base_url = f"http://{host}:{port}"

    env = os.environ.copy()
    # IMPORTANT: keep session cookies stable & decodable
    env["SECRET_KEY"] = "test-secret-key"
    env["FLASK_SECRET_KEY"] = "test-secret-key"

    # Ensure imports resolve from the copied app dir
    env["PYTHONPATH"] = str(app_dir)

    # Start server ON THE CHOSEN PORT (single start)
    proc = subprocess.Popen(
        [
            sys.executable,
            "-c",
            (
                "from app import app; "
                f"app.run(host='{host}', port={port}, debug=False, use_reloader=False)"
            ),
        ],
        cwd=str(app_dir),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        env=env,
    )

    # wait for readiness
    deadline = time.time() + 12
    while time.time() < deadline:
        try:
            r = requests.get(base_url + "/login", timeout=0.5)
            if r.status_code in (200, 302):
                break
        except Exception:
            time.sleep(0.1)
    else:
        proc.kill()
        raise RuntimeError("Flask server did not start")

    try:
        yield {
            "app_dir": app_dir,
            "base_url": base_url,
            "db_path": db_path,
            "uploads_dir": uploads_dir,
        }
    finally:
        proc.kill()
        try:
            proc.wait(timeout=3)
        except Exception:
            pass