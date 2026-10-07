"""Stored XSS check: comment body should be escaped on render."""

from common import api_login_admin, api_register_and_login_student, api_create_course, api_add_member, api_create_post, api_create_comment, html_get


def test_stored_xss_in_comment_is_escaped():
    admin = api_login_admin()
    course = api_create_course(admin, title='comment-xss-course')

    stu_s, stu = api_register_and_login_student()
    api_add_member(admin, int(course['course_id']), int(stu['user_id']), 'student')

    post = api_create_post(stu_s, int(course['course_id']), title='post', body='body')

    payload = '<svg onload=alert(1)>'
    api_create_comment(stu_s, int(course['course_id']), int(post['post_id']), body=payload)

    html = html_get(stu_s, f"/courses/{course['course_id']}/posts/{post['post_id']}")
    assert payload not in html
    assert '&lt;svg onload=alert(1)&gt;' in html
