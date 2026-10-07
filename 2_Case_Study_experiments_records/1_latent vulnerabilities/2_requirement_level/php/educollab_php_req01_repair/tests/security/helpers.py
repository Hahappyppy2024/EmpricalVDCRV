import json
import sqlite3
from urllib.parse import urlparse

import requests


def open_db(db_path):
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    return conn


def api_url(base_url: str, path: str) -> str:
    return base_url.rstrip("/") + path


def json_of(resp):
    try:
        return resp.json()
    except Exception as exc:
        raise AssertionError(f"Response is not JSON: status={resp.status_code}, body={resp.text}") from exc


def register(session: requests.Session, base_url: str, username: str, password: str, display_name: str):
    return session.post(
        api_url(base_url, "/api/auth/register"),
        json={
            "username": username,
            "password": password,
            "display_name": display_name,
        },
        timeout=10,
    )


def login(session: requests.Session, base_url: str, username: str, password: str):
    return session.post(
        api_url(base_url, "/api/auth/login"),
        json={
            "username": username,
            "password": password,
        },
        timeout=10,
    )


def logout(session: requests.Session, base_url: str):
    return session.post(api_url(base_url, "/api/auth/logout"), timeout=10)


def me(session: requests.Session, base_url: str):
    return session.get(api_url(base_url, "/api/auth/me"), timeout=10)


def create_course(session: requests.Session, base_url: str, title: str, description: str):
    return session.post(
        api_url(base_url, "/api/courses"),
        json={"title": title, "description": description},
        timeout=10,
    )


def update_course(session: requests.Session, base_url: str, course_id: int, title: str, description: str):
    return session.put(
        api_url(base_url, f"/api/courses/{course_id}"),
        json={"title": title, "description": description},
        timeout=10,
    )


def delete_course(session: requests.Session, base_url: str, course_id: int):
    return session.delete(api_url(base_url, f"/api/courses/{course_id}"), timeout=10)


def get_course(session: requests.Session, base_url: str, course_id: int):
    return session.get(api_url(base_url, f"/api/courses/{course_id}"), timeout=10)


def get_user_id(conn, username: str):
    row = conn.execute(
        "SELECT user_id FROM users WHERE username = ?",
        (username,),
    ).fetchone()
    return None if row is None else int(row["user_id"])


def get_course_id(conn, title: str):
    row = conn.execute(
        "SELECT course_id FROM courses WHERE title = ? ORDER BY course_id DESC LIMIT 1",
        (title,),
    ).fetchone()
    return None if row is None else int(row["course_id"])


def set_php_session_cookie(session: requests.Session, base_url: str, session_id: str):
    parsed = urlparse(base_url)
    host = parsed.hostname or "127.0.0.1"
    secure = parsed.scheme == "https"

    session.cookies.set(
        "PHPSESSID",
        session_id,
        domain=host,
        path="/",
        secure=secure,
    )


def current_php_session_id(session: requests.Session, base_url: str):
    parsed = urlparse(base_url)
    host = parsed.hostname or "127.0.0.1"
    for cookie in session.cookies:
        if cookie.name == "PHPSESSID" and cookie.domain in (host, f".{host}"):
            return cookie.value
    for cookie in session.cookies:
        if cookie.name == "PHPSESSID":
            return cookie.value
    return None