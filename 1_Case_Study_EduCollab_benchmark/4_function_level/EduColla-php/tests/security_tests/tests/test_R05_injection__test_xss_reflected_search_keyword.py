"""Reflected XSS check: search keyword should be escaped when echoed back on the HTML page."""

import urllib.parse
from common import api_login_admin, api_register_and_login_student, api_create_course, api_add_member, html_get


def test_reflected_xss_in_search_keyword_is_escaped():
    admin = api_login_admin()
    course = api_create_course(admin, title='search-xss-course')

    stu_s, stu = api_register_and_login_student()
    api_add_member(admin, int(course['course_id']), int(stu['user_id']), 'student')

    payload = '<img src=x onerror=alert(1)>'
    q = urllib.parse.quote(payload)
    html = html_get(stu_s, f"/courses/{course['course_id']}/search?keyword={q}")

    assert payload not in html
    assert '&lt;img src=x onerror=alert(1)&gt;' in html
