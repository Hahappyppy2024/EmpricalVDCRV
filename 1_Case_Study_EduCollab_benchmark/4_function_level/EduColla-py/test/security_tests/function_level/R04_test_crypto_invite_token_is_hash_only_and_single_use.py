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


def test_crypto_invite_token_is_hash_only_and_single_use(client, app):
    # Admin create course + invite
    _login_html(client, "admin", "admin123")
    client.post("/courses/new", data={"title": "SingleUse Course", "description": "D"}, follow_redirects=True)
    course = db_one(client, "SELECT course_id FROM course WHERE title='SingleUse Course' ORDER BY course_id DESC")
    course_id = course["course_id"]

    rv = api_post_json(client, f"/api/courses/{course_id}/invites", {"role_in_course": "student", "ttl_minutes": 60}, expected_status=(200,))
    invite_link = rv.get_json()["invite_link"]
    token = _extract_token(invite_link)

    # DB should not store plaintext token
    rows = db_all(client, "SELECT token_hash FROM invite_token")
    assert rows, "invite_token table should have at least one row"
    token_hash = rows[-1]["token_hash"]
    assert re.match(r"^[a-f0-9]{64}$", token_hash, re.I)
    assert token_hash != token
    assert token not in str([dict(r) for r in rows])

    # Register bob and join (first use)
    _register_html(client, "bob_sec", "password", "Bob Sec")
    _login_html(client, "bob_sec", "password")
    rv = api_post_json(client, "/api/join", {"token": token}, expected_status=(200,))
    assert rv.get_json()["joined"] is True

    # Register charlie and try reuse (should fail)
    _register_html(client, "charlie_sec", "password", "Charlie Sec")
    _login_html(client, "charlie_sec", "password")
    rv = client.post("/api/join", json={"token": token})
    assert rv.status_code in (400, 403)
