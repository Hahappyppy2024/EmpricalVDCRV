import shutil
import socket
import subprocess
import time
from pathlib import Path

import pytest
import requests


def _find_app_root(base_dir: Path) -> Path:
    if (base_dir / "public").is_dir():
        return base_dir

    children = [p for p in base_dir.iterdir() if p.is_dir()]
    if len(children) == 1 and (children[0] / "public").is_dir():
        return children[0]

    raise RuntimeError(f"Could not find PHP app root with public/ under {base_dir}")


@pytest.fixture(scope="session")
def source_app_dir() -> Path:
    return Path(__file__).resolve().parent.parent


@pytest.fixture()
def app_instance(tmp_path, source_app_dir):
    copied_dir = tmp_path / "app_under_test"
    shutil.copytree(
        source_app_dir,
        copied_dir,
        ignore=shutil.ignore_patterns(".venv", "venv", "__pycache__", ".pytest_cache", "tests"),
    )

    app_root = _find_app_root(copied_dir)

    db_path = app_root / "data" / "app.db"
    db_path.parent.mkdir(parents=True, exist_ok=True)
    if db_path.exists():
        db_path.unlink()

    storage_dir = app_root / "storage"
    if storage_dir.exists():
        shutil.rmtree(storage_dir)

    host = "127.0.0.1"
    sock = socket.socket()
    sock.bind((host, 0))
    port = sock.getsockname()[1]
    sock.close()

    proc = subprocess.Popen(
        ["php", "-S", f"{host}:{port}", "-t", "public"],
        cwd=str(app_root),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    base_url = f"http://{host}:{port}"
    session = requests.Session()

    deadline = time.time() + 10
    last_text = None
    while time.time() < deadline:
        try:
            resp = session.get(base_url + "/", timeout=1)
            last_text = resp.text
            if resp.status_code in (200, 404, 500):
                break
        except Exception:
            time.sleep(0.2)
    else:
        proc.terminate()
        raise RuntimeError(f"PHP built-in server failed to start. Last response: {last_text}")

    yield {
        "app_dir": app_root,
        "base_url": base_url,
        "proc": proc,
        "db_path": db_path,
    }

    session.close()
    proc.terminate()
    try:
        proc.wait(timeout=5)
    except subprocess.TimeoutExpired:
        proc.kill()
        proc.wait(timeout=5)


@pytest.fixture()
def client(app_instance):
    return requests.Session(), app_instance["base_url"]