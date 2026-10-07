from util import BASE_URL, rand, api_register, api_login, api_create_course, api_add_member, api_create_assignment

def test_import_grades_should_require_staff():
    admin_s, r = api_login("admin","admin123")
    assert r.status_code==200
    c=api_create_course(admin_s, title=rand("c")).json()["course"]["course_id"]
    a=api_create_assignment(admin_s, c, title=rand("a")).json()["assignment"]["assignment_id"]

    stu=rand("stu")
    api_register(stu,"pw",stu)
    stus,_=api_login(stu,"pw")
    uid=stus.get(BASE_URL+"/api/auth/me").json()["user"]["user_id"]
    api_add_member(admin_s, c, uid, "student")

    csv_content = "username,score,feedback\n%s,100,ok\n" % stu
    files={"file": ("grades.csv", csv_content.encode("utf-8"))}
    r=stus.post(f"{BASE_URL}/api/courses/{c}/assignments/{a}/grades/import.csv", files=files)
    assert r.status_code in (401,403,404), r.text
