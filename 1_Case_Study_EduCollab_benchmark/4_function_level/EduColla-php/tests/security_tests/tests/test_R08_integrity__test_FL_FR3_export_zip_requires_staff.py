from util import BASE_URL, rand, api_register, api_login, api_create_course, api_add_member, api_create_assignment

def test_export_zip_should_require_staff():
    admin_s, r = api_login("admin", "admin123")
    assert r.status_code == 200
    r = api_create_course(admin_s, title=rand("c"))
    course_id = r.json()["course"]["course_id"]
    r = api_create_assignment(admin_s, course_id, title=rand("a"))
    assignment_id = r.json()["assignment"]["assignment_id"]

    # student member
    stu = rand("stu")
    api_register(stu, "pw", stu)
    stus, _ = api_login(stu, "pw")
    uid = stus.get(BASE_URL + "/api/auth/me").json()["user"]["user_id"]
    api_add_member(admin_s, course_id, uid, "student")

    r = stus.post(f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/export-zip")
    assert r.status_code in (401,403,404), r.text
