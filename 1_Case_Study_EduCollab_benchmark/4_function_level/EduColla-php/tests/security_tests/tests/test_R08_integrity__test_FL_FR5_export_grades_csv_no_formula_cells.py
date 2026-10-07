from util import BASE_URL, rand, api_register, api_login, api_create_course, api_add_member, api_create_assignment
import io

def test_export_grades_csv_should_not_contain_formula_cells():
    admin_s, r = api_login("admin","admin123")
    assert r.status_code==200
    c=api_create_course(admin_s, title=rand("c")).json()["course"]["course_id"]
    a=api_create_assignment(admin_s, c, title=rand("a")).json()["assignment"]["assignment_id"]

    # student member
    stu=rand("stu")
    api_register(stu,"pw",stu)
    stus,_=api_login(stu,"pw")
    uid=stus.get(BASE_URL+"/api/auth/me").json()["user"]["user_id"]
    api_add_member(admin_s, c, uid, "student")

    # import grades CSV with feedback starting with '='
    csv_content = "username,score,feedback\n%s,100,=1+1\n" % stu
    files={"file": ("grades.csv", csv_content.encode("utf-8"))}
    r=admin_s.post(f"{BASE_URL}/api/courses/{c}/assignments/{a}/grades/import.csv", files=files)
    assert r.status_code==200, r.text

    r=admin_s.get(f"{BASE_URL}/api/courses/{c}/assignments/{a}/grades.csv")
    assert r.status_code==200
    body=r.text
    # Expected secure behavior: no cell should begin with =,+,-,@
    bad = any((",=1+1" in line) or line.strip().endswith(",=1+1") for line in body.splitlines())
    assert not bad, body
