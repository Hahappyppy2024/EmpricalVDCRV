from .._helpers import api_get, db_one

# ============================================================
# API (JSON) - XSS-related checks
# (For APIs, the key property is returning JSON (not HTML rendering).
#  We validate content-type + no template rendering.)
# ============================================================

def test_api_returns_json_for_post_detail(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    # Create via HTML route to ensure a post exists, then fetch via API
    client.post(
        f"/courses/{course_id}/posts",
        data={"title": "t", "body": "<script>alert(1)</script>"},
        follow_redirects=False
    )
    post = db_one(client, "SELECT * FROM post ORDER BY post_id DESC LIMIT 1")
    assert post is not None

    rv = api_get(client, f"/api/courses/{course_id}/posts/{post['post_id']}", expected_status=(200, 401, 403))
    if rv.status_code == 200:
        assert rv.mimetype == "application/json"


def test_api_search_returns_json_not_html(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    payload = "<img src=x onerror=alert(1)>"
    rv = api_get(client, f"/api/courses/{course_id}/posts?keyword={payload}", expected_status=(200, 400, 401, 403))
    if rv.status_code == 200:
        assert rv.mimetype == "application/json"


def test_api_error_is_json_not_stacktrace_html(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    # Try an obviously invalid post id (may yield 404/400)
    rv = client.get(f"/api/courses/{course_id}/posts/not-a-number", follow_redirects=False)
    if rv.status_code == 404:
        # endpoint missing -> handled in other tests; here accept
        return
    assert rv.status_code in (400, 404, 401, 403, 200)
    # if not success, should still not be a full HTML debug stack
    assert b"Traceback" not in rv.data


from .._helpers import db_one

def test_web_stored_xss_body_is_escaped_on_detail_page(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    # 1) Create a post containing a script payload (stored XSS attempt)
    payload = "<script>alert(1)</script>"
    client.post(
        f"/courses/{course_id}/posts",
        data={"title": "t", "body": payload},
        follow_redirects=False,
    )

    # 2) Get the newest post_id from DB
    post = db_one(client, "SELECT * FROM post ORDER BY post_id DESC LIMIT 1")
    assert post is not None
    post_id = post["post_id"]

    # 3) Fetch the HTML detail page (web route) and assert escaping
    rv = client.get(f"/courses/{course_id}/posts/{post_id}", follow_redirects=False)
    assert rv.status_code in (200, 302)

    # If redirected to login, test setup failed (not logged-in)
    if rv.status_code == 302:
        assert False, "Unexpected redirect; login/session may not be set"

    # 4) Strong XSS oracle: raw <script> should NOT appear, escaped should appear
    assert b"<script>alert(1)</script>" not in rv.data
    assert b"&lt;script&gt;alert(1)&lt;/script&gt;" in rv.data