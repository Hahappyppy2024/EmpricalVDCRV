import requests

from helpers import (
    app_instance,
    client,
    open_db,
    ok,
    register,
    login,
    logout,
    create_course,
    get_course_id,
    api_url,
)

def test_requirement_01_bootstrapping_auth_user_course(client, app_instance):
    session, base_url = client
    conn = open_db(app_instance["db_path"])

    try:
        # seed admin
        row = conn.execute(
            "SELECT user_id, username FROM users WHERE username='admin'"
        ).fetchone()
        assert row is not None
        assert row["user_id"] is not None

        # register
        r = register(session, base_url, "req1_user", "pass123", "Req1 User")
        ok(r)

        row = conn.execute(
            "SELECT user_id, display_name FROM users WHERE username=?",
            ("req1_user",),
        ).fetchone()
        assert row is not None
        assert row["display_name"] == "Req1 User"

        # me
        r = session.get(api_url(base_url, "/api/auth/me"), timeout=5, allow_redirects=False)
        ok(r)

        # admin login
        admin = requests.Session()
        admin.headers.update({"Accept": "application/json"})
        r = login(admin, base_url, "admin", "admin123")
        ok(r)

        # list users
        r = admin.get(api_url(base_url, "/api/users"), timeout=5, allow_redirects=False)
        ok(r)

        # create/list/get/update/delete course
        r = create_course(admin, base_url, "Req1 Course", "Requirement 01 course")
        ok(r)

        course_id = get_course_id(conn, "Req1 Course")
        assert course_id is not None

        r = admin.get(api_url(base_url, "/api/courses"), timeout=5, allow_redirects=False)
        ok(r)

        r = admin.get(api_url(base_url, f"/api/courses/{course_id}"), timeout=5, allow_redirects=False)
        ok(r)

        r = admin.put(
            api_url(base_url, f"/api/courses/{course_id}"),
            json={"title": "Req1 Course Updated", "description": "Requirement 01 course updated"},
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT title, description FROM courses WHERE course_id=?",
            (course_id,),
        ).fetchone()
        assert row["title"] == "Req1 Course Updated"
        assert row["description"] == "Requirement 01 course updated"

        r = admin.delete(
            api_url(base_url, f"/api/courses/{course_id}"),
            timeout=5,
            allow_redirects=False,
        )
        ok(r)

        row = conn.execute(
            "SELECT course_id FROM courses WHERE course_id=?",
            (course_id,),
        ).fetchone()
        assert row is None

        # logout
        r = logout(session, base_url)
        ok(r)

        r = session.get(api_url(base_url, "/api/auth/me"), timeout=5, allow_redirects=False)
        assert r.status_code in (401, 302)
    finally:
        conn.close()