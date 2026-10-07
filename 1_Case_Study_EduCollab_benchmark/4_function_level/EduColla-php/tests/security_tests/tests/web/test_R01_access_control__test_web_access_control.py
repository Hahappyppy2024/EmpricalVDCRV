\
"""
Web (HTML) Security Tests — Access Control

These tests verify basic access-control behavior for HTML routes:
- Auth required: unauthenticated users should be redirected to /login or denied.
- Membership required: non-members should not access course pages.
- Role separation: students should be denied from staff-only HTML pages (e.g., create assignment).
"""

import requests
from common import (
    BASE_URL,
    api_login_admin,
    api_register_and_login_student,
    api_create_course,
    api_add_member,
    html_get_raw,
)


def test_html_courses_requires_login():
    """
    Tests: HTML route /courses requires authentication.

    Expected:
    - 302 redirect to /login, OR 401/403 depending on implementation.
    """
    s = requests.Session()
    r = html_get_raw(s, "/courses")
    assert r.status_code in (302, 401, 403)
    if r.status_code == 302:
        assert "/login" in r.headers.get("Location", "").lower()


def test_html_course_detail_denies_non_member():
    """
    Tests: Membership gate on HTML route /courses/{course_id}.

    Expected:
    - non-member should be denied (403/404), or redirected (302).
    """
    admin = api_login_admin()
    course = api_create_course(admin, title="private-course")

    outsider_s, _ = api_register_and_login_student()
    r = html_get_raw(outsider_s, f"/courses/{course['course_id']}")
    assert r.status_code in (302, 403, 404)


def test_html_assignment_new_denies_student_role():
    """
    Tests: Role-based access control on staff-only HTML page /courses/{course_id}/assignments/new.

    Expected:
    - student should be denied (403/404) or redirected (302).
    """
    admin = api_login_admin()
    course = api_create_course(admin, title="role-course")

    stu_s, stu = api_register_and_login_student()
    api_add_member(admin, int(course["course_id"]), int(stu["user_id"]), "student")

    r = html_get_raw(stu_s, f"/courses/{course['course_id']}/assignments/new")
    assert r.status_code in (302, 403, 404)
