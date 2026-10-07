import os
import shutil
import socket
import sqlite3
import subprocess
import time
from pathlib import Path

import pytest
import requests


PROJECT_ROOT = Path(__file__).resolve().parents[2]
PUBLIC_DIR = PROJECT_ROOT / "public"
DB_PATH = PROJECT_ROOT / "data" / "app.db"
UPLOAD_DIR = PROJECT_ROOT / "storage" / "uploads"


def api_url(base_url: str, path: str) -> str:
    return f"{base_url}{path}"


def reset_test_state():
    if DB_PATH.exists():
        DB_PATH.unlink()

    if UPLOAD_DIR.exists():
        shutil.rmtree(UPLOAD_DIR, ignore_errors=True)
    UPLOAD_DIR.mkdir(parents=True, exist_ok=True)


def get_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def wait_for_server(base_url: str, timeout: float = 10.0):
    start = time.time()
    last_error = None
    while time.time() - start < timeout:
        try:
            r = requests.get(api_url(base_url, "/api/courses"), timeout=1, allow_redirects=False)
            if r.status_code in (200, 401, 403):
                return
        except Exception as e:
            last_error = e
        time.sleep(0.2)
    raise RuntimeError(f"Server did not become ready within {timeout}s. last_error={last_error}")


@pytest.fixture
def app_instance():
    reset_test_state()
    port = get_free_port()
    base_url = f"http://127.0.0.1:{port}"

    cmd = ["php", "-S", f"127.0.0.1:{port}", "-t", str(PUBLIC_DIR)]
    proc = subprocess.Popen(
        cmd,
        cwd=str(PROJECT_ROOT),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )

    try:
        wait_for_server(base_url)
        yield {
            "base_url": base_url,
            "db_path": str(DB_PATH),
            "uploads_dir": str(UPLOAD_DIR),
            "proc": proc,
        }
    finally:
        if proc.poll() is None:
            proc.terminate()
            try:
                proc.wait(timeout=3)
            except subprocess.TimeoutExpired:
                proc.kill()


@pytest.fixture
def client(app_instance):
    s = requests.Session()
    s.headers.update({"Accept": "application/json"})
    return s, app_instance["base_url"]


def open_db(db_path: str):
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    return conn


def ok(resp):
    assert resp.status_code in (200, 201, 302), (resp.status_code, resp.text[:500])


def login(session: requests.Session, base_url: str, username: str, password: str):
    return session.post(
        api_url(base_url, "/api/auth/login"),
        json={"username": username, "password": password},
        timeout=5,
        allow_redirects=False,
    )


def register(session: requests.Session, base_url: str, username: str, password: str, display_name: str):
    return session.post(
        api_url(base_url, "/api/auth/register"),
        json={
            "username": username,
            "password": password,
            "display_name": display_name,
        },
        timeout=5,
        allow_redirects=False,
    )


def logout(session: requests.Session, base_url: str):
    return session.post(
        api_url(base_url, "/api/auth/logout"),
        json={},
        timeout=5,
        allow_redirects=False,
    )


def get_user_id(conn, username: str):
    row = conn.execute(
        "SELECT user_id FROM users WHERE username=?",
        (username,),
    ).fetchone()
    return row["user_id"] if row else None


def create_course(session: requests.Session, base_url: str, title: str, description: str):
    return session.post(
        api_url(base_url, "/api/courses"),
        json={"title": title, "description": description},
        timeout=5,
        allow_redirects=False,
    )


def get_course_id(conn, title: str):
    row = conn.execute(
        "SELECT course_id FROM courses WHERE title=? ORDER BY course_id DESC",
        (title,),
    ).fetchone()
    return row["course_id"] if row else None


def add_member(session: requests.Session, base_url: str, course_id: int, user_id: int, role_in_course: str):
    return session.post(
        api_url(base_url, f"/api/courses/{course_id}/members"),
        json={"user_id": user_id, "role_in_course": role_in_course},
        timeout=5,
        allow_redirects=False,
    )