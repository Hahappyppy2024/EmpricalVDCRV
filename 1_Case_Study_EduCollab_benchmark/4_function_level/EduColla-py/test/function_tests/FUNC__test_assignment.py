from database import get_db_connection
from conftest import add_membership


def test_assignment_and_submission_workflow(app, client, auth, admin_logged_in, course_with_admin, student_user):
    course_id = course_with_admin
    add_membership(app, course_id, student_user, "student")

    rv = client.post(
        f"/courses/{course_id}/assignments",
        data={"title": "Asg1", "description": "D"},
        follow_redirects=False,
    )
    assert rv.status_code == 302

    with app.app_context():
        conn = get_db_connection()
        asg = conn.execute("SELECT * FROM assignment ORDER BY assignment_id DESC LIMIT 1").fetchone()
        conn.close()
        assert asg is not None
        asg_id = asg["assignment_id"]

    auth.logout()
    rv = auth.login("student", "pass")
    assert rv.status_code in (200, 302)

    rv = client.post(
        f"/courses/{course_id}/assignments/{asg_id}/submissions",
        data={"content_text": "My work"},
        follow_redirects=False,
    )
    assert rv.status_code in (302, 201)

    with app.app_context():
        conn = get_db_connection()
        sub = conn.execute(
            "SELECT * FROM submission WHERE assignment_id=? AND student_id=?",
            (asg_id, student_user),
        ).fetchone()
        conn.close()
        assert sub is not None
        assert sub["content_text"] == "My work"
