from util import BASE_URL, rand, api_register, api_login, api_create_course, api_add_member, api_create_assignment, api_upload_file, api_create_submission, make_zip_bytes

def test_list_files_should_not_be_public_to_non_member():
    admin_s, r = api_login("admin", "admin123")
    assert r.status_code == 200

    r = api_create_course(admin_s, title=rand("c"))
    course_id = r.json()["course"]["course_id"]
    r = api_create_assignment(admin_s, course_id, title=rand("a"))
    assignment_id = r.json()["assignment"]["assignment_id"]

    # student1 member
    s1 = rand("s1")
    api_register(s1, "pw", s1)
    s1s, _ = api_login(s1, "pw")
    uid1 = s1s.get(BASE_URL + "/api/auth/me").json()["user"]["user_id"]
    api_add_member(admin_s, course_id, uid1, "student")

    # upload zip & submit
    zip_bytes = make_zip_bytes([("a.txt","a")])
    up = api_upload_file(admin_s, course_id, "a.zip", zip_bytes).json()["upload"]["upload_id"]
    sub = api_create_submission(s1s, course_id, assignment_id, "x", up).json()["submission"]["submission_id"]
    admin_s.post(f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/{sub}/unzip")

    # student2 NOT a member
    s2 = rand("s2")
    api_register(s2, "pw", s2)
    s2s, _ = api_login(s2, "pw")

    r = s2s.get(f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/{sub}/files")
    # Expected: 401/403/404. Vulnerable if 200 with files.
    assert r.status_code in (401,403,404), r.text
