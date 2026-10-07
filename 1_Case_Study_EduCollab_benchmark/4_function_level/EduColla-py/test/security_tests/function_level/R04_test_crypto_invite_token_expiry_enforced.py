import re
from .._helpers import db_all, db_one, api_post_json, ensure_user_and_membership
import pytest

def _extract_token(invite_link: str) -> str:
    m = re.search(r"token=([^&]+)", invite_link)
    assert m, f"Cannot extract token from {invite_link}"
    return m.group(1)

def _login_html(client, username, password):
    return client.post("/login", data={"username": username, "password": password}, follow_redirects=True)

def _register_html(client, username, password, display_name):
    return client.post("/register", data={"username": username, "password": password, "display_name": display_name}, follow_redirects=True)

def test_crypto_invite_token_expiry_enforced(client, app):
    _login_html(client, "admin", "admin123")
    client.post("/courses/new", data={"title": "Expiry Course", "description": "D"}, follow_redirects=True)
    course = db_one(client, "SELECT course_id FROM course WHERE title='Expiry Course' ORDER BY course_id DESC")
    course_id = course["course_id"]

    rv = api_post_json(client, f"/api/courses/{course_id}/invites", {"role_in_course": "student", "ttl_minutes": 60}, expected_status=(200,))
    token = _extract_token(rv.get_json()["invite_link"])

    # Force expiry in DB
    with client.application.app_context():
        from database import get_db_connection
        conn = get_db_connection()
        conn.execute("UPDATE invite_token SET expires_at = datetime('now','-10 minutes')")
        conn.commit()
        conn.close()

    _register_html(client, "dave_sec", "password", "Dave Sec")
    _login_html(client, "dave_sec", "password")
    rv = client.post("/api/join", json={"token": token})
    assert rv.status_code in (400, 403)
