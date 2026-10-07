from database import get_db_connection


def test_post_create_and_detail_html(app, client, admin_logged_in, course_with_admin):
    course_id = course_with_admin
    rv = client.post(
        f"/courses/{course_id}/posts",
        data={"title": "Hello", "body": "Body"},
        follow_redirects=False,
    )
    assert rv.status_code == 302

    with app.app_context():
        conn = get_db_connection()
        p = conn.execute("SELECT * FROM post ORDER BY post_id DESC LIMIT 1").fetchone()
        conn.close()
        assert p is not None
        post_id = p["post_id"]

    rv = client.get(f"/courses/{course_id}/posts/{post_id}", follow_redirects=True)
    assert rv.status_code == 200
    assert b"Hello" in rv.data


def test_api_post_requires_auth(app, course_with_admin):
    course_id = course_with_admin
    # use a fresh client with no session
    anon = app.test_client()
    rv = anon.get(f"/api/courses/{course_id}/posts")
    assert rv.status_code == 401


def test_api_create_post_json(app, client, auth, course_with_admin):
    course_id = course_with_admin
    auth.api_login("admin", "admin123")

    rv = client.post(
        f"/api/courses/{course_id}/posts",
        json={"title": "API Post", "body": "B"},
    )
    assert rv.status_code == 201

    with app.app_context():
        conn = get_db_connection()
        p = conn.execute("SELECT * FROM post WHERE title='API Post'").fetchone()
        conn.close()
        assert p is not None
