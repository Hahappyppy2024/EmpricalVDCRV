import os
import io
import re

import pytest
from flask import current_app
from werkzeug.security import generate_password_hash

from database import get_db_connection


# -----------------------
# DB helpers (always under app_context)
# -----------------------
def db_one(client, sql, params=()):
    with client.application.app_context():
        conn = get_db_connection()
        row = conn.execute(sql, params).fetchone()
        conn.close()
        return row

def db_all(client, sql, params=()):
    with client.application.app_context():
        conn = get_db_connection()
        rows = conn.execute(sql, params).fetchall()
        conn.close()
        return rows

def ensure_user_and_membership(client, course_id, username, password, display_name, role):
    with client.application.app_context():
        conn = get_db_connection()
        conn.execute(
            "INSERT OR IGNORE INTO user (username, password_hash, display_name) VALUES (?, ?, ?)",
            (username, generate_password_hash(password), display_name)
        )
        conn.commit()
        u = conn.execute("SELECT user_id FROM user WHERE username = ?", (username,)).fetchone()
        assert u is not None
        user_id = u["user_id"]

        conn.execute(
            "INSERT OR IGNORE INTO membership (course_id, user_id, role_in_course) VALUES (?, ?, ?)",
            (course_id, user_id, role)
        )
        conn.commit()
        conn.close()
        return user_id


# -----------------------
# API helpers (skip gracefully if /api not implemented)
# -----------------------
def api_post_json(client, url, payload, expected_status=None, follow_redirects=False):
    rv = client.post(url, json=payload, follow_redirects=follow_redirects)
    print("POST", url, "->", rv.status_code, rv.data[:200])  # <-- 临时加
    if rv.status_code == 404:
        pytest.skip(f"API endpoint not implemented: {url}")
    if expected_status is not None:
        assert rv.status_code in expected_status
    return rv
def api_get(client, url, expected_status=None, follow_redirects=False):
    rv = client.get(url, follow_redirects=follow_redirects)
    if rv.status_code == 404:
        pytest.skip(f"API endpoint not implemented: {url}")
    if expected_status is not None:
        assert rv.status_code in expected_status
    return rv
#
# def api_login(client, username, password):
#     # Preferred API auth route
#     rv = api_post_json(client, "/api/login", {"username": username, "password": password}, expected_status=(200, 302, 400, 401, 403))
#     return rv
#
# def api_logout(client):
#     # Common patterns: POST /api/logout or GET /api/logout
#     rv = client.post("/api/logout")
#     if rv.status_code == 404:
#         rv = client.get("/api/logout")
#         if rv.status_code == 404:
#             pytest.skip("API logout endpoint not implemented: /api/logout")
#     return rv
# def api_login(client, username, password):
#     # Try common API auth routes in order
#     for path in ("/api/auth/login", "/api/login"):
#         rv = api_post_json(
#             client,
#             path,
#             {"username": username, "password": password},
#             expected_status=(200, 201, 302, 400, 401, 403),
#         )
#         # api_post_json 会在 404 时 skip；但我们这里希望尝试下一个而不是 skip
#         # 所以不要用 api_post_json 的 404-skip 行为（见下方“更稳版本”）
#         return rv
import pytest

def api_login(client, username, password):
    for path in ("/api/auth/login", "/api/login"):
        rv = client.post(path, json={"username": username, "password": password}, follow_redirects=False)

        if rv.status_code == 404:
            continue  # try next candidate

        # Accept a broad set because implementations vary
        assert rv.status_code in (200, 201, 302, 400, 401, 403), f"Unexpected status {rv.status_code} for {path}"
        return rv

    pytest.skip("API login endpoint not implemented: tried /api/auth/login and /api/login")

def api_logout(client):
    # Try common logout routes in order
    for path in ("/api/auth/logout", "/api/logout"):
        rv = client.post(path)
        if rv.status_code != 404:
            return rv
        rv = client.get(path)
        if rv.status_code != 404:
            return rv

    pytest.skip("API logout endpoint not implemented: /api/auth/logout or /api/logout")