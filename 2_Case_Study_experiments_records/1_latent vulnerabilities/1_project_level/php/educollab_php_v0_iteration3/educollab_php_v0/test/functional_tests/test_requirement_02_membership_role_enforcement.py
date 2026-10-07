import requests

from helpers import (
    app_instance,
    client,
    open_db,
    ok,
    register,
    login,
    create_course,
    get_course_id,
    get_user_id,
    add_member,
    api_url,
)

def test_requirement_02_membership_role_enforcement(client, app_instance):
    _, base_url = client
    conn = open_db(app_instance["db_path"])

    admin = requests.Session()
    teacher = requests.Session()
    student = requests.Session()

    for s in (admin, teacher, student):
        s.headers.update({"Accept": "application/json"})

    try:
        ok(login(admin, base_url, "admin", "admin123"))

        ok(register(teacher, base_url, "req2_teacher", "pass123", "Req2 Teacher"))
        ok(register(student, base_url, "req2_student", "pass123", "Req2 Student"))

        teacher_id = get_user_id(conn, "req2_teacher")
        student_id = get_user_id(conn, "req2_student")
        assert teacher_id is not None
        assert student_id is not None

        ok(create_course(admin, base_url, "Req2 Course", "Membership requirement course"))
        course_id = get_course_id(conn, "Req2 Course")
        assert course_id is not None

        # admin adds teacher
        r = add_member(admin, base_url, course_id, teacher_id, "teacher")
        ok(r)

        row = conn.execute(
            "SELECT membership_id, role_in_course FROM memberships WHERE course_id=? AND user_id=?",
            (course_id, teacher_id),
        ).fetchone()
        assert row is not None
        assert row["role_in_course"] == "teacher"

        # teacher can view members
        r = teacher.get(api_url(base_url, f"/api/courses/{course_id}/members"), timeout=5, allow_redirects=False)
        ok(r)

        # teacher adds student
        r = add_member(teacher, base_url, course_id, student_id, "student")
        ok(r)

        row = conn.execute(
            "SELECT membership_id, role_in_course FROM memberships WHERE course_id=? AND user_id=?",
            (course_id, student_id),
        ).fetchone()
        assert row is not None
        assert row["role_in_course"] == "student"
        membership_id = row["membership_id"]

        # student can view members
        r = student.get(api_url(base_url, f"/api/courses/{course_id}/members"), timeout=5, allow_redirects=False)
        ok(r)

        # teacher updates role
        r = teacher.put(
            api_url(base_url, f"/api/courses/{course_id}/members/{membership_id}"),
            json={"role_in_course": "assistant"},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT role_in_course FROM memberships WHERE membership_id=?",
            (membership_id,),
        ).fetchone()
        assert row["role_in_course"] == "assistant"

        # my memberships
        r = student.get(api_url(base_url, "/api/memberships"), timeout=5, allow_redirects=False)
        ok(r)

        # teacher removes student
        r = teacher.delete(
            api_url(base_url, f"/api/courses/{course_id}/members/{membership_id}"),
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT membership_id FROM memberships WHERE membership_id=?",
            (membership_id,),
        ).fetchone()
        assert row is None
    finally:
        conn.close()