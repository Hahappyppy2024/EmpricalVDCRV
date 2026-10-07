import requests
from conftest import BASE_URL
from util import (
    api_login, api_register, api_users, find_user_id,
    api_create_course, api_add_member, api_create_assignment, api_create_submission, rand
)

def _get_first_working(admin: requests.Session, paths: list[str]) -> requests.Response:
    """
    Try multiple candidate endpoints and return the first non-404 response.
    This makes the test compatible across variants where the route name differs.
    """
    last = None
    for p in paths:
        r = admin.get(f"{BASE_URL}{p}", allow_redirects=False)
        last = r
        if r.status_code != 404:
            return r
    return last  # all 404, return last for error message

def test_RL_CSV3_export_assignment_grades_functional():
    """
    Functional check: export assignment grades as CSV.

    What it verifies:
    - Admin can export grades CSV for a course assignment.
    - Endpoint returns a successful response (200).
    - Response is not a PHP fatal error page.
    - CSV/text contains the student's username (at least as a row/field).

    Compatibility:
    - Some variants expose:
        GET /api/courses/{course_id}/assignments/{assignment_id}/grades.csv
      Others might expose:
        GET /api/courses/{course_id}/assignments/{assignment_id}/grades/export
    - This test tries both.
    """
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course_id = api_create_course(admin, "C_" + rand("c"), "desc")

    # Register student
    stu = requests.Session()
    uname = rand("stu")
    api_register(stu, uname, "pw12345", "Student")

    # Add student to course (admin side)
    uid = find_user_id(api_users(admin), uname)
    assert uid is not None, "Newly registered user not found via /api/users"
    api_add_member(admin, course_id, uid, "student")

    # Create assignment (admin side)
    assignment_id = api_create_assignment(admin, course_id, "A_" + rand("a"), "desc")

    # Student submits
    api_login(stu, uname, "pw12345")
    api_create_submission(stu, course_id, assignment_id, "my submission text")

    # Try known endpoints across variants
    paths = [
        f"/api/courses/{course_id}/assignments/{assignment_id}/grades.csv",   # matches your index.php
        f"/api/courses/{course_id}/assignments/{assignment_id}/grades/export" # fallback for other variants
    ]
    r = _get_first_working(admin, paths)

    # If both are 404, fail with clear message
    if r.status_code == 404:
        raise AssertionError(
            f"No grades export endpoint found. Tried: {paths}. "
            f"Got 404 body_head={(r.text or '')[:200]}"
        )

    assert r.status_code == 200, (r.text or "")[:300]

    text = r.text or ""

    # If the endpoint crashes, PHP dev server often returns HTML "Fatal error" (sometimes still 200)
    if "Fatal error" in text:
        raise AssertionError(
            "grades export endpoint crashed (Fatal error). "
            "Check CsvController.php (and any repo method it calls). "
            f"body_head={text[:300]}"
        )

    # Should look like CSV/text (not a full HTML page)
    ct = r.headers.get("Content-Type", "")
    if "text/html" in ct.lower() and ("<html" in text.lower() or "<!doctype" in text.lower()):
        raise AssertionError(
            f"Expected CSV/text but got HTML. ct={ct} body_head={text[:300]}"
        )

    # Minimal content sanity: should contain newlines or commas
    assert ("," in text) or ("\n" in text), text[:300]

    # Main check: student's username should appear somewhere in exported content
    assert uname in text, text[:300]