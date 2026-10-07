import requests
from datetime import datetime, timezone
from _util import BASE_URL, rand, api_login, api_register, api_create_course, api_add_member

def _now_iso():
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

def test_FR_CSV3_export_grades_neutralizes_formula_cells():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course = api_create_course(admin, title="c_"+rand(), description="d")
    cid = course["course_id"]

    # create teacher + student
    teacher = requests.Session()
    tname = "t_"+rand()
    api_register(teacher, tname, "pw12345", "Teacher")
    api_login(teacher, tname, "pw12345")

    student = requests.Session()
    sname = "s_"+rand()
    api_register(student, sname, "pw12345", "Student")
    api_login(student, sname, "pw12345")

    # lookup ids via admin /api/users (stable)
    users = admin.get(f"{BASE_URL}/api/users").json().get("users", [])
    t_id = next((int(u["user_id"]) for u in users if u.get("username")==tname), None)
    s_id = next((int(u["user_id"]) for u in users if u.get("username")==sname), None)
    assert t_id is not None and s_id is not None

    api_add_member(admin, cid, t_id, "teacher")
    api_add_member(admin, cid, s_id, "student")

    # teacher creates assignment (due_at must be string)
    r = teacher.post(
        f"{BASE_URL}/api/courses/{cid}/assignments",
        json={"title":"A1","description":"d","due_at": _now_iso()},
        allow_redirects=False
    )
    assert r.status_code in (200,201), r.text
    aid = r.json()["assignment"]["assignment_id"]

    # student submits formula-like content
    payload = {"content_text": '=HYPERLINK("http://evil","x")', "attachment_upload_id": None}
    r = student.post(
        f"{BASE_URL}/api/courses/{cid}/assignments/{aid}/submissions",
        json=payload,
        allow_redirects=False
    )
    assert r.status_code in (200,201), r.text

    # export grades CSV (aligned route)
    r = admin.get(f"{BASE_URL}/api/courses/{cid}/assignments/{aid}/grades/export", allow_redirects=False)
    assert r.status_code == 200, r.text[:200]
    text = r.text

    # Secure expectation: formula should be neutralized (e.g., prefixed with ')
    assert "=HYPERLINK" not in text, text