import requests
from conftest import BASE_URL
from util_fl import (
    api_login, api_register, api_users, find_user_id_by_username,
    api_create_course, api_add_member, api_create_assignment,
    api_create_submission, rand
)

def test_FL_FR5_export_grades_csv_functional():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course_id = api_create_course(admin, "C_" + rand("c"), "desc")

    # student + membership
    stu = requests.Session()
    uname = rand("stu")
    api_register(stu, uname, "pw12345", "Student")
    uid = find_user_id_by_username(api_users(admin), uname)
    assert uid is not None
    api_add_member(admin, course_id, uid, "student")

    assignment_id = api_create_assignment(admin, course_id, "A_" + rand("a"), "desc")

    api_login(stu, uname, "pw12345")
    api_create_submission(stu, course_id, assignment_id, "submission for grades")

    # The only route guaranteed by your project-level index.php is grades.csv
    urls = [
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/grades.csv",
        f"{BASE_URL}/courses/{course_id}/assignments/{assignment_id}/grades.csv",
    ]

    resp = None
    for url in urls:
        r = admin.get(url, allow_redirects=False)
        if r.status_code != 404:
            resp = r
            break

    if resp is None:
        # All candidates are 404 => route missing in this variant OR BASE_URL points to wrong server
        raise AssertionError(
            f"Not Found for all grades export candidates. Tried: {urls}. "
            f"BASE_URL={BASE_URL} (check you're running the correct variant and port)."
        )

    assert resp.status_code == 200, f"status={resp.status_code} url={resp.request.url} body_head={(resp.text or '')[:200]}"

    text = resp.text or ""
    if "Fatal error" in text:
        raise AssertionError(f"grades.csv crashed (Fatal error). url={resp.request.url} body_head={text[:300]}")

    # Content sanity (csv/text)
    assert ("," in text) or ("\n" in text), text[:200]
    # If export includes submitters regardless of grading, username should appear
    assert uname in text, text[:300]