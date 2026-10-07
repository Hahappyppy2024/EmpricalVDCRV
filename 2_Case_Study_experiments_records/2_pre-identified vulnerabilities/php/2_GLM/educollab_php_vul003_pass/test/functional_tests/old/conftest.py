import shutil
import socket
import subprocess
import time
from pathlib import Path

import pytest
import requests


def _pick_free_port(host: str = "127.0.0.1") -> int:
    sock = socket.socket()
    sock.bind((host, 0))
    port = sock.getsockname()[1]
    sock.close()
    return port


@pytest.fixture(scope="session")
def source_app_dir() -> Path:
    """
    Resolve the PHP project root from this file location.

    Expected layout:
      educollab_php_v0/
        public/
        app/
        test/
          functional_tests/
            conftest.py
            test_function_correctness.py
    """
    project_root = Path(__file__).resolve().parents[2]
    if not (project_root / "public").exists():
        raise RuntimeError(f"Project root looks wrong: {project_root}")
    return project_root


@pytest.fixture()
def app_instance(tmp_path, source_app_dir):
    """
    Copy the app into a fresh temp directory and start PHP built-in server.
    """
    app_dir = tmp_path / "educollab_php_v0"
    shutil.copytree(source_app_dir, app_dir)

    data_dir = app_dir / "data"
    data_dir.mkdir(parents=True, exist_ok=True)

    db_path = data_dir / "app.db"
    if db_path.exists():
        db_path.unlink()

    storage_dir = app_dir / "storage"
    if storage_dir.exists():
        shutil.rmtree(storage_dir)
    (storage_dir / "uploads").mkdir(parents=True, exist_ok=True)

    host = "127.0.0.1"
    port = _pick_free_port(host)

    server_log = tmp_path / "php_server.log"
    log_fp = open(server_log, "w", encoding="utf-8")

    proc = subprocess.Popen(
        ["php", "-S", f"{host}:{port}", "-t", "public"],
        cwd=str(app_dir),
        stdout=log_fp,
        stderr=log_fp,
    )

    base_url = f"http://{host}:{port}"
    deadline = time.time() + 15
    started = False

    while time.time() < deadline:
        if proc.poll() is not None:
            break
        try:
            r = requests.get(base_url + "/login", timeout=1, allow_redirects=False)
            if r.status_code in (200, 302):
                started = True
                break
        except requests.RequestException:
            time.sleep(0.2)

    if not started:
        try:
            proc.terminate()
            proc.wait(timeout=3)
        except Exception:
            try:
                proc.kill()
            except Exception:
                pass

        log_fp.close()
        log_text = ""
        try:
            log_text = server_log.read_text(encoding="utf-8", errors="replace")
        except Exception:
            pass

        raise RuntimeError(
            "PHP built-in server failed to start.\n"
            f"app_dir={app_dir}\n"
            f"log_file={server_log}\n"
            f"log_output:\n{log_text}"
        )

    try:
        yield {
            "app_dir": app_dir,
            "base_url": base_url,
            "db_path": db_path,
            "server_log": server_log,
        }
    finally:
        try:
            proc.terminate()
            proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=5)
        finally:
            log_fp.close()


@pytest.fixture()
def client(app_instance):
    s = requests.Session()
    s.base_url = app_instance["base_url"]
    return s


@pytest.fixture()
def base_url(app_instance):
    return app_instance["base_url"]


@pytest.fixture()
def app_dir(app_instance):
    return app_instance["app_dir"]