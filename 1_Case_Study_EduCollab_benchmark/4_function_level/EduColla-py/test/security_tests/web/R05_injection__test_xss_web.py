from .._helpers import db_one

# ============================================================
# Web (HTML) - XSS
# ============================================================

def test_web_stored_xss_body_is_escaped_on_detail_page(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    xss = "<script>alert('XSS');</script>"

    rv = client.post(
        f"/courses/{course_id}/posts",
        data={"title": "Safe Title", "body": xss},
        follow_redirects=False
    )
    assert rv.status_code == 302

    post = db_one(client, "SELECT * FROM post ORDER BY post_id DESC LIMIT 1")
    assert post is not None
    post_id = post["post_id"]

    rv = client.get(f"/courses/{course_id}/posts/{post_id}", follow_redirects=True)
    assert rv.status_code == 200

    assert b"<script>alert('XSS');</script>" not in rv.data
    assert (b"&lt;script&gt;" in rv.data) or (b"&#60;script&#62;" in rv.data)


def test_web_reflected_xss_keyword_is_escaped(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    payload = "<img src=x onerror=alert(1)>"
    rv = client.get(f"/courses/{course_id}/posts?keyword={payload}", follow_redirects=True)

    assert rv.status_code in (200, 400)
    assert b"<img src=x onerror=alert(1)>" not in rv.data
    # at least some escaped token should appear if keyword is echoed
    assert (b"&lt;img" in rv.data) or (b"&#60;img" in rv.data) or (b"&amp;lt;img" in rv.data)


def test_web_xss_title_is_escaped_on_list_page(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    title = "<svg onload=alert(1)>"
    rv = client.post(
        f"/courses/{course_id}/posts",
        data={"title": title, "body": "body"},
        follow_redirects=True
    )
    assert rv.status_code == 200

    # list page should not render raw title as executable markup
    assert b"<svg onload=alert(1)>" not in rv.data
