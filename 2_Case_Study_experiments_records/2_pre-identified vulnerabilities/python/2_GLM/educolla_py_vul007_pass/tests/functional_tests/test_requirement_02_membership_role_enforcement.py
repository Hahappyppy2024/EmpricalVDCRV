from helpers import (
    open_db,
    ok,
    register,
    login,
    create_course,
    get_course_id,
    get_user_id,
    add_member,
    json_of,
)


def test_requirement_02_membership_role_enforcement(client, app_instance):
    conn = open_db()
    try:
        ok(login(client, "admin", "admin123"))

        ok(register(client, "req2_teacher", "pass123", "Req2 Teacher"))
        ok(register(client, "req2_student", "pass123", "Req2 Student"))

        teacher_id = get_user_id(conn, "req2_teacher")
        student_id = get_user_id(conn, "req2_student")
        assert teacher_id is not None
        assert student_id is not None

        # IMPORTANT: re-login admin because register may change session
        ok(login(client, "admin", "admin123"))

        ok(create_course(client, "Req2 Course", "Membership requirement course"))
        course_id = get_course_id(conn, "Req2 Course")
        assert course_id is not None

        # admin adds teacher
        r = add_member(client, course_id, teacher_id, "teacher")
        ok(r)
        teacher_membership_id = json_of(r)["membership_id"]

        row = conn.execute(
            "SELECT membership_id, role_in_course FROM memberships WHERE membership_id=?",
            (teacher_membership_id,),
        ).fetchone()
        assert row is not None
        assert row["role_in_course"] == "teacher"

        # list members
        r = client.get(f"/api/courses/{course_id}/members")
        ok(r)
        payload = json_of(r)
        assert "members" in payload
        assert any(m["user_id"] == teacher_id for m in payload["members"])

        # admin adds student
        r = add_member(client, course_id, student_id, "student")
        ok(r)
        student_membership_id = json_of(r)["membership_id"]

        row = conn.execute(
            "SELECT membership_id, role_in_course FROM memberships WHERE membership_id=?",
            (student_membership_id,),
        ).fetchone()
        assert row is not None
        assert row["role_in_course"] == "student"

        # teacher updates role (more realistic role flow)
        ok(login(client, "req2_teacher", "pass123"))

        r = client.put(
            f"/api/courses/{course_id}/members/{student_membership_id}",
            json={"role_in_course": "assistant"},
        )
        ok(r)

        row = conn.execute(
            "SELECT role_in_course FROM memberships WHERE membership_id=?",
            (student_membership_id,),
        ).fetchone()
        assert row["role_in_course"] == "assistant"

        # student view memberships
        ok(login(client, "req2_student", "pass123"))

        r = client.get("/api/memberships")
        ok(r)
        assert "memberships" in json_of(r)

        # teacher remove member
        ok(login(client, "req2_teacher", "pass123"))

        r = client.delete(f"/api/courses/{course_id}/members/{student_membership_id}")
        ok(r)

        row = conn.execute(
            "SELECT membership_id FROM memberships WHERE membership_id=?",
            (student_membership_id,),
        ).fetchone()
        assert row is None

    finally:
        conn.close()