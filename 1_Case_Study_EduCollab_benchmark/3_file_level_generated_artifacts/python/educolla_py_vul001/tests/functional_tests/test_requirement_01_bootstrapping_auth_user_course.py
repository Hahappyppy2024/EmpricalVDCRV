from helpers import (
    open_db,
    ok,
    json_of,
    register,
    login,
    logout,
    create_course,
    get_course_id,
)


def test_requirement_01_bootstrapping_auth_user_course(client, app_instance):
    conn = open_db()
    try:
        # seed admin
        row = conn.execute(
            "SELECT user_id, username FROM users WHERE username='admin'"
        ).fetchone()
        assert row is not None
        assert row["user_id"] is not None

        # register student
        r = register(client, "req1_user", "pass123", "Req1 User")
        ok(r)
        payload = json_of(r)
        assert payload["username"] == "req1_user"

        row = conn.execute(
            "SELECT user_id, display_name FROM users WHERE username=?",
            ("req1_user",),
        ).fetchone()
        assert row is not None
        assert row["display_name"] == "Req1 User"

        # me
        r = client.get("/api/auth/me")
        ok(r)
        assert json_of(r)["user"]["username"] == "req1_user"

        # logout
        r = logout(client)
        ok(r)

        r = client.get("/api/auth/me")
        assert r.status_code == 401

        # login admin
        r = login(client, "admin", "admin123")
        ok(r)
        assert json_of(r)["user"]["username"] == "admin"

        # list users
        r = client.get("/api/users")
        ok(r)
        payload = json_of(r)
        assert "users" in payload
        assert any(u["username"] == "admin" for u in payload["users"])

        # create course
        r = create_course(client, "Req1 Course", "Requirement 01 course")
        ok(r)

        course_id = get_course_id(conn, "Req1 Course")
        assert course_id is not None

        # list courses
        r = client.get("/api/courses")
        ok(r)
        payload = json_of(r)
        assert "courses" in payload
        assert any(c["course_id"] == course_id for c in payload["courses"])

        # get course
        r = client.get(f"/api/courses/{course_id}")
        ok(r)
        assert json_of(r)["course"]["course_id"] == course_id

        # update course
        r = client.put(
            f"/api/courses/{course_id}",
            json={
                "title": "Req1 Course Updated",
                "description": "Requirement 01 course updated",
            },
        )
        ok(r)

        row = conn.execute(
            "SELECT title, description FROM courses WHERE course_id=?",
            (course_id,),
        ).fetchone()
        assert row["title"] == "Req1 Course Updated"
        assert row["description"] == "Requirement 01 course updated"

        # delete course
        r = client.delete(f"/api/courses/{course_id}")
        ok(r)

        row = conn.execute(
            "SELECT course_id FROM courses WHERE course_id=?",
            (course_id,),
        ).fetchone()
        assert row is None
    finally:
        conn.close()