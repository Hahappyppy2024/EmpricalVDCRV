import importlib.util
import shutil
import sqlite3
import sys
import uuid
from pathlib import Path

import pytest


@pytest.fixture(scope="session")
def source_app_dir() -> Path:
    return Path(__file__).resolve().parent.parent


@pytest.fixture()
def app_instance(tmp_path, source_app_dir, monkeypatch):
    app_dir = tmp_path / "educollab_py_r5"
    shutil.copytree(
        source_app_dir,
        app_dir,
        ignore=shutil.ignore_patterns(".venv", "venv", "__pycache__", ".pytest_cache", "tests"),
    )

    monkeypatch.chdir(app_dir)
    sys.path.insert(0, str(app_dir))
    try:
        module_name = f"app_under_test_{uuid.uuid4().hex}"
        spec = importlib.util.spec_from_file_location(module_name, app_dir / "app.py")
        module = importlib.util.module_from_spec(spec)
        assert spec is not None and spec.loader is not None
        spec.loader.exec_module(module)

        flask_app = module.create_app() if hasattr(module, "create_app") else module.app
        flask_app.config.update(TESTING=True)

        yield {
            "app": flask_app,
            "root": app_dir,
            "db_path": app_dir / "data" / "app.db",
            "upload_root": app_dir / "storage" / "uploads",
        }
    finally:
        try:
            sys.path.remove(str(app_dir))
        except ValueError:
            pass


@pytest.fixture()
def client(app_instance):
    return app_instance["app"].test_client()


def api_register(client, username, password="pass123", display_name=None):
    return client.post(
        "/api/auth/register",
        json={
            "username": username,
            "password": password,
            "display_name": display_name or username,
        },
    )


def api_login(client, username, password="pass123"):
    return client.post(
        "/api/auth/login",
        json={"username": username, "password": password},
    )


def api_logout(client):
    return client.post("/api/auth/logout")


def create_course(client, title="Course A", description="Desc"):
    return client.post(
        "/api/courses",
        json={"title": title, "description": description},
    )


def _resolve_db_path(app_like):
    if isinstance(app_like, dict):
        return app_like["db_path"]
    return Path(app_like.config["DATABASE"])


def get_user_id_by_username(app_instance, username):
    db_path = _resolve_db_path(app_instance)
    conn = sqlite3.connect(db_path)
    try:
        row = conn.execute(
            "SELECT user_id FROM users WHERE username = ?",
            (username,),
        ).fetchone()
        return row[0] if row else None
    finally:
        conn.close()


def get_membership_id(app_instance, course_id, user_id):
    db_path = _resolve_db_path(app_instance)
    conn = sqlite3.connect(db_path)
    try:
        row = conn.execute(
            "SELECT membership_id FROM memberships WHERE course_id = ? AND user_id = ?",
            (course_id, user_id),
        ).fetchone()
        return row[0] if row else None
    finally:
        conn.close()