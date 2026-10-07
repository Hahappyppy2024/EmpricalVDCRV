from util import BASE_URL, rand, api_register, api_login, api_create_course, api_add_member, api_create_assignment

def test_download_export_zip_should_not_be_cross_course_reusable():
    admin_s, r = api_login("admin","admin123")
    assert r.status_code==200

    # course1
    r=api_create_course(admin_s, title=rand("c1"))
    c1=r.json()["course"]["course_id"]
    a1=api_create_assignment(admin_s, c1, title=rand("a1")).json()["assignment"]["assignment_id"]
    job=admin_s.post(f"{BASE_URL}/api/courses/{c1}/assignments/{a1}/submissions/export-zip").json()["job_id"]

    # course2
    r=api_create_course(admin_s, title=rand("c2"))
    c2=r.json()["course"]["course_id"]
    a2=api_create_assignment(admin_s, c2, title=rand("a2")).json()["assignment"]["assignment_id"]

    # student member only in course2
    stu=rand("stu")
    api_register(stu,"pw",stu)
    stus,_=api_login(stu,"pw")
    uid=stus.get(BASE_URL+"/api/auth/me").json()["user"]["user_id"]
    api_add_member(admin_s, c2, uid, "student")

    # try download using course2 path but course1 job id
    r=stus.get(f"{BASE_URL}/api/courses/{c2}/assignments/{a2}/submissions/export-zip/{job}/download")
    assert r.status_code in (401,403,404), f"cross-scope download succeeded: {r.status_code}"
