import io
import sqlite3
from pathlib import Path

from db import DB_PATH


def open_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def ok(resp):
    assert resp.status_code in (200, 201, 302), resp.get_data(as_text=True)


def json_of(resp):
    return resp.get_json()


def register(client, username, password, display_name):
    return client.post(
        "/api/auth/register",
        json={
            "username": username,
            "password": password,
            "display_name": display_name,
        },
    )


def login(client, username, password):
    return client.post(
        "/api/auth/login",
        json={"username": username, "password": password},
    )


def logout(client):
    return client.post("/api/auth/logout", json={})


def create_course(client, title, description):
    return client.post(
        "/api/courses",
        json={"title": title, "description": description},
    )


def add_member(client, course_id, user_id, role_in_course):
    return client.post(
        f"/api/courses/{course_id}/members",
        json={"user_id": user_id, "role_in_course": role_in_course},
    )


def get_user_id(conn, username):
    row = conn.execute(
        "SELECT user_id FROM users WHERE username=?",
        (username,),
    ).fetchone()
    return row["user_id"] if row else None


def get_course_id(conn, title):
    row = conn.execute(
        "SELECT course_id FROM courses WHERE title=? ORDER BY course_id DESC",
        (title,),
    ).fetchone()
    return row["course_id"] if row else None


def upload_file(client, course_id, filename, content: bytes, content_type="text/plain"):
    data = {
        "file": (io.BytesIO(content), filename),
    }
    return client.post(
        f"/api/courses/{course_id}/uploads",
        data=data,
        content_type="multipart/form-data",
    )