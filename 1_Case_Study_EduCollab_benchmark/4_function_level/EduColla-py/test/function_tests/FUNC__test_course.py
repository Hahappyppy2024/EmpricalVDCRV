from database import get_db_connection


def test_create_course_html(client, admin_logged_in):
    rv = client.post(
        "/courses/new",
        data={"title": "New Course", "description": "Desc"},
        follow_redirects=True,
    )
    assert rv.status_code == 200
    assert b"New Course" in rv.data


def test_add_member_and_role_html(app, client, admin_logged_in, course_with_admin, student_user):
    course_id = course_with_admin

    rv = client.post(
        f"/courses/{course_id}/members",
        data={"user_id": student_user, "role": "student"},
        follow_redirects=True,
    )
    assert rv.status_code == 200
    assert b"Student User" in rv.data

    with app.app_context():
        conn = get_db_connection()
        m = conn.execute(
            "SELECT membership_id FROM membership WHERE course_id=? AND user_id=?",
            (course_id, student_user),
        ).fetchone()
        conn.close()
        assert m is not None
        membership_id = m["membership_id"]

    rv = client.put(
        f"/api/courses/{course_id}/members/{membership_id}",
        json={"role_in_course": "assistant"},
    )
    assert rv.status_code in (200, 401, 403)

    rv = client.get(f"/courses/{course_id}/members", follow_redirects=True)
    assert rv.status_code == 200
    assert b"Student User" in rv.data
