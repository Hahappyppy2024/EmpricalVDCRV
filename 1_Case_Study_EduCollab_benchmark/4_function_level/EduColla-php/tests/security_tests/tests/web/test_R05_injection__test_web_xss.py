\
"""
Web (HTML) Security Tests — XSS

These tests verify that user-controlled content is HTML-escaped when rendered on HTML pages.
- Stored XSS: attacker stores payload in DB (post/comment), victim views HTML page.
- Reflected XSS: attacker injects payload via URL query (search keyword), victim views HTML page.
"""

import urllib.parse
from common import (
    api_login_admin,
    api_register_and_login_student,
    api_create_course,
    api_add_member,
    api_create_post,
    api_create_comment,
    html_get,
)


def test_stored_xss_post_title_body_are_escaped_on_detail_page():
    """
    Tests: Stored XSS prevention on HTML post detail page.

    Steps:
    - Create course and add a student.
    - Student creates a post whose title/body contain a <script> payload.
    - Fetch HTML detail page and assert:
        (1) raw payload is NOT present
        (2) escaped payload IS present
    """
    admin = api_login_admin()
    course = api_create_course(admin, title="xss-course")

    stu_s, stu = api_register_and_login_student()
    api_add_member(admin, int(course["course_id"]), int(stu["user_id"]), "student")

    payload = '<script>alert("XSS")</script>'
    post = api_create_post(stu_s, int(course["course_id"]), title=f"Hello {payload}", body=f"Body {payload}")

    html = html_get(stu_s, f"/courses/{course['course_id']}/posts/{post['post_id']}")

    assert payload not in html
    assert '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;' in html


def test_stored_xss_comment_body_is_escaped_on_post_detail_page():
    """
    Tests: Stored XSS prevention on HTML comment rendering.

    Steps:
    - Create course, add student, create a post.
    - Student creates a comment containing an <svg onload=...> payload.
    - Fetch HTML post detail page and assert payload is escaped.
    """
    admin = api_login_admin()
    course = api_create_course(admin, title="comment-xss-course")

    stu_s, stu = api_register_and_login_student()
    api_add_member(admin, int(course["course_id"]), int(stu["user_id"]), "student")

    post = api_create_post(stu_s, int(course["course_id"]), title="post", body="body")

    payload = "<svg onload=alert(1)>"
    api_create_comment(stu_s, int(course["course_id"]), int(post["post_id"]), body=payload)

    html = html_get(stu_s, f"/courses/{course['course_id']}/posts/{post['post_id']}")

    assert payload not in html
    assert "&lt;svg onload=alert(1)&gt;" in html


def test_reflected_xss_search_keyword_is_escaped_on_html_search_page():
    """
    Tests: Reflected XSS prevention on HTML search page.

    Steps:
    - Create course and add a student.
    - Student visits HTML search page with a malicious keyword query param.
    - Assert raw payload not present and escaped payload present.
    """
    admin = api_login_admin()
    course = api_create_course(admin, title="search-xss-course")

    stu_s, stu = api_register_and_login_student()
    api_add_member(admin, int(course["course_id"]), int(stu["user_id"]), "student")

    payload = "<img src=x onerror=alert(1)>"
    q = urllib.parse.quote(payload)
    html = html_get(stu_s, f"/courses/{course['course_id']}/search?keyword={q}")

    assert payload not in html
    assert "&lt;img src=x onerror=alert(1)&gt;" in html
