"""Stored XSS check: post title/body should be HTML-escaped on render."""

from common import api_login_admin, api_register_and_login_student, api_create_course, api_add_member, api_create_post, html_get


def test_stored_xss_in_post_detail_is_escaped():
    admin = api_login_admin()
    course = api_create_course(admin, title='xss-course')

    stu_s, stu = api_register_and_login_student()
    api_add_member(admin, int(course['course_id']), int(stu['user_id']), 'student')

    payload = '<script>alert("XSS")</script>'
    post = api_create_post(stu_s, int(course['course_id']), title=f"Hello {payload}", body=f"Body {payload}")

    html = html_get(stu_s, f"/courses/{course['course_id']}/posts/{post['post_id']}")

    # should not contain raw tag
    assert payload not in html
    # should contain escaped version
    assert '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;' in html
