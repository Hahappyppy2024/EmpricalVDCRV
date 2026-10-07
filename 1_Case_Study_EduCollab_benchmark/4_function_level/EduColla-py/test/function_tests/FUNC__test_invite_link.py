import re
from database import get_db_connection

def _extract_token(invite_link: str) -> str:
    # invite_link format: /join?token=...
    m = re.search(r"token=([^&]+)", invite_link)
    assert m, f"Cannot extract token from {invite_link}"
    return m.group(1)

def test_invite_link_join_flow(app, client, auth, admin_logged_in):
    # Create course via HTML flow (admin is logged in)
    rv = client.post(
        "/courses/new",
        data={"title": "Invite Course", "description": "Desc"},
        follow_redirects=True,
    )
    assert rv.status_code == 200

    # Get created course_id
    with app.app_context():
        conn = get_db_connection()
        course = conn.execute("SELECT course_id FROM course WHERE title='Invite Course' ORDER BY course_id DESC").fetchone()
        conn.close()
        assert course is not None
        course_id = course["course_id"]

    # Create invite via API (staff)
    rv = client.post(f"/api/courses/{course_id}/invites", json={"role_in_course": "student", "ttl_minutes": 60})
    assert rv.status_code == 200
    data = rv.get_json()
    assert "invite_link" in data
    token = _extract_token(data["invite_link"])

    # Register and login bob
    auth.register("bob", "password", "Bob")
    auth.login("bob", "password")

    # Join via API
    rv = client.post("/api/join", json={"token": token})
    assert rv.status_code == 200
    j = rv.get_json()
    assert j["joined"] is True
    assert j["course_id"] == course_id

    # Verify bob membership exists
    with app.app_context():
        conn = get_db_connection()
        bob = conn.execute("SELECT user_id FROM user WHERE username='bob'").fetchone()
        assert bob is not None
        m = conn.execute("SELECT * FROM membership WHERE course_id=? AND user_id=?", (course_id, bob["user_id"])).fetchone()
        conn.close()
        assert m is not None
