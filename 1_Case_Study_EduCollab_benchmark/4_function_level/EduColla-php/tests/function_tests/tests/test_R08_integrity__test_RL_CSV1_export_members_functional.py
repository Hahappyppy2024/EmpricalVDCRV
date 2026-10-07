import requests
from conftest import BASE_URL
from util import api_login, api_create_course, api_register, api_users, find_user_id, api_add_member, rand

def test_RL_CSV1_export_members_functional():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")

    course_id = api_create_course(admin, "C_" + rand("c"), "desc")

    # create a student and add to course
    stu = requests.Session()
    uname = rand("stu")
    api_register(stu, uname, "pw12345", "Student")
    uid = find_user_id(api_users(admin), uname)
    assert uid is not None
    api_add_member(admin, course_id, uid, "student")

    # try known endpoints across variants
    paths = [
        f"/api/courses/{course_id}/members/export.csv",  # matches your index.php
        f"/api/courses/{course_id}/members/export",      # fallback for other variants
    ]

    last = None
    for p in paths:
        r = admin.get(f"{BASE_URL}{p}", allow_redirects=False)
        last = r
        if r.status_code != 404:
            break

    assert last is not None
    r = last

    assert r.status_code == 200, (r.text or "")[:200]

    # If the endpoint crashes, PHP dev server often returns HTML "Fatal error" (sometimes still 200)
    text = r.text or ""
    if "Fatal error" in text:
        raise AssertionError(f"members export endpoint crashed (Fatal error). body_head={text[:300]}")

    # basic CSV/text sanity
    assert "username" in text.lower(), text[:200]
    assert uname in text, text[:200]