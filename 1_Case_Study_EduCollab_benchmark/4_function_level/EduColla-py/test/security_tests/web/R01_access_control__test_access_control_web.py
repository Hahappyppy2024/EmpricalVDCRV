from .._helpers import db_one, ensure_user_and_membership

# ============================================================
# Web (HTML) - Access Control
# ============================================================

def test_web_student_cannot_create_assignment(client, auth, sample_course):
    course_id = sample_course
    ensure_user_and_membership(client, course_id, "student_acl", "pass", "Student ACL", "student")
    auth.login("student_acl", "pass")

    rv = client.post(
        f"/courses/{course_id}/assignments",
        data={"title": "Should Fail", "description": "no"},
        follow_redirects=False
    )

    # Depending on implementation: 403 (recommended) or redirect to login or 404
    assert rv.status_code in (302, 403, 404)


def test_web_non_member_cannot_view_course_posts(client, auth, sample_course):
    course_id = sample_course
    auth.register("outsider", "pass", "Out Sider")
    auth.login("outsider", "pass")

    rv = client.get(f"/courses/{course_id}/posts", follow_redirects=False)
    # Typical: 403 or redirect, some apps choose 404 to hide existence
    assert rv.status_code in (302, 403, 404)


def test_web_submission_cannot_modify_other_students_submission(client, auth, sample_course):
    course_id = sample_course

    student_a_id = ensure_user_and_membership(client, course_id, "student_a", "pass", "Student A", "student")
    student_b_id = ensure_user_and_membership(client, course_id, "student_b", "pass", "Student B", "student")

    auth.login("teacher", "password")
    rv = client.post(
        f"/courses/{course_id}/assignments",
        data={"title": "Secure Assignment", "description": "Test"},
        follow_redirects=False
    )
    assert rv.status_code == 302

    asg = db_one(client, "SELECT * FROM assignment ORDER BY assignment_id DESC LIMIT 1")
    assert asg is not None
    asg_id = asg["assignment_id"]

    auth.login("student_a", "pass")
    rv = client.post(
        f"/courses/{course_id}/assignments/{asg_id}/submissions",
        data={"content_text": "A work"},
        follow_redirects=False
    )
    assert rv.status_code in (302, 201)

    sub_a = db_one(client, "SELECT * FROM submission WHERE student_id = ? AND assignment_id = ?",
                   (student_a_id, asg_id))
    assert sub_a is not None
    a_submission_id = sub_a["submission_id"]

    auth.login("student_b", "pass")
    rv = client.post(
        f"/courses/{course_id}/assignments/{asg_id}/submissions",
        data={"content_text": "B tries to hack A"},
        follow_redirects=False
    )
    assert rv.status_code in (302, 201)

    sub_a_after = db_one(client, "SELECT * FROM submission WHERE submission_id = ?", (a_submission_id,))
    assert sub_a_after is not None
    assert sub_a_after["content_text"] == "A work"

    sub_b = db_one(client, "SELECT * FROM submission WHERE student_id = ? AND assignment_id = ?",
                   (student_b_id, asg_id))
    assert sub_b is not None
