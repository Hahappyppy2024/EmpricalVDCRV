import os
import sqlite3
import sys
import threading
from pathlib import Path

import pytest
from werkzeug.serving import make_server

# conftest.py -> security_tests -> tests -> project_root
PROJECT_ROOT = Path(__file__).resolve().parents[2]
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

import app as target_app  # noqa: E402
import db as target_db  # noqa: E402


class _ServerThread(threading.Thread):
    def __init__(self, app, host="127.0.0.1", port=0):
        super().__init__(daemon=True)
        self._server = make_server(host, port, app)
        self.host = host
        self.port = self._server.server_port
        self.ctx = app.app_context()
        self.ctx.push()

    def run(self):
        self._server.serve_forever()

    def shutdown(self):
        self._server.shutdown()
        self.ctx.pop()


@pytest.fixture()
def app(tmp_path, monkeypatch):
    db_file = tmp_path / "app.db"

    monkeypatch.setattr(target_db, "DB_PATH", str(db_file), raising=True)
    if hasattr(target_app, "DB_PATH"):
        monkeypatch.setattr(target_app, "DB_PATH", str(db_file), raising=False)

    # 如果项目依赖这些环境变量，可一并设置；没有也不会影响大多数项目
    monkeypatch.setenv("EDUCOLLAB_SECRET_KEY", "test-secret-key")
    monkeypatch.setenv("EDUCOLLAB_INITIAL_ADMIN_PASSWORD", "admin123")
    monkeypatch.setenv("EDUCOLLAB_ADMIN_USERNAME", "admin")

    target_app.app.config.update(TESTING=True)

    # 尝试初始化数据库（按项目实际实现择优调用）
    if hasattr(target_db, "init_db"):
        try:
            target_db.init_db()
        except TypeError:
            try:
                target_db.init_db(str(db_file))
            except Exception:
                pass
        except Exception:
            pass

    if hasattr(target_app, "init_db"):
        try:
            target_app.init_db()
        except TypeError:
            try:
                target_app.init_db(str(db_file))
            except Exception:
                pass
        except Exception:
            pass

    yield target_app.app


@pytest.fixture()
def client(app):
    return app.test_client()


@pytest.fixture()
def app_instance(app):
    server = _ServerThread(app)
    server.start()
    try:
        yield {
            "base_url": f"http://{server.host}:{server.port}",
            "db_path": target_db.DB_PATH,
        }
    finally:
        server.shutdown()


def _json_headers(extra=None):
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if extra:
        headers.update(extra)
    return headers


def register(client, username, password="pw123456", display_name=None):
    payload = {
        "username": username,
        "password": password,
        "display_name": display_name or username,
    }
    return client.post("/api/auth/register", json=payload, headers=_json_headers())


def login(client, username, password="pw123456"):
    return client.post(
        "/api/auth/login",
        json={"username": username, "password": password},
        headers=_json_headers(),
    )


def register_and_login(client, username, password="pw123456", display_name=None):
    r = register(client, username, password=password, display_name=display_name)
    assert r.status_code in (200, 201), r.get_data(as_text=True)

    l = login(client, username, password)
    assert l.status_code == 200, l.get_data(as_text=True)

    body = l.get_json()
    assert body["ok"] is True
    assert body["user"]["username"] == username
    return body["user"]["user_id"]


def create_course(client, title, description="demo"):
    resp = client.post(
        "/api/courses",
        json={"title": title, "description": description},
        headers=_json_headers(),
    )
    assert resp.status_code == 200, resp.get_data(as_text=True)
    return resp.get_json()["course_id"]


def add_member(client, course_id, user_id, role):
    resp = client.post(
        f"/api/courses/{course_id}/members",
        json={"user_id": user_id, "role_in_course": role},
        headers=_json_headers(),
    )
    assert resp.status_code == 200, resp.get_data(as_text=True)
    return resp.get_json()["membership_id"]


def get_db_conn():
    conn = sqlite3.connect(target_db.DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn