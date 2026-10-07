import io, csv
import requests
from _util import BASE_URL, rand, api_login, api_register, api_create_course, api_add_member, api_list_members

def test_FR_CSV2_import_does_not_allow_role_override():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course = api_create_course(admin, title="c_"+rand(), description="d")
    cid = course["course_id"]

    # Create a student who will attempt the import
    s = requests.Session()
    importer = "imp_"+rand()
    api_register(s, importer, "pw12345", "Importer")
    api_login(s, importer, "pw12345")

    # Add importer as student in the course (so they're a course member but not staff)
    # lookup user_id via /api/users (admin)
    users = admin.get(f"{BASE_URL}/api/users").json().get("users", [])
    importer_id = next((int(u["user_id"]) for u in users if u.get("username") == importer), None)
    assert importer_id is not None
    api_add_member(admin, cid, importer_id, "student")

    # CSV tries to import a user as teacher (privilege escalation attempt)
    new_user = "new_"+rand()
    buf = io.StringIO()
    w = csv.writer(buf)
    # Align to controller: username,password,display_name,role_in_course
    w.writerow(["username","password","display_name","role_in_course"])
    w.writerow([new_user, "pw12345", "New User", "teacher"])
    buf.seek(0)

    files = {"csv": ("members.csv", buf.getvalue().encode("utf-8"), "text/csv")}
    r = s.post(f"{BASE_URL}/api/courses/{cid}/members/import", files=files, allow_redirects=False)

    # Secure expectation:
    # - Either only staff can import (401/403)
    # - OR role column is ignored/neutralized (import OK but role becomes student)
    if r.status_code in (200, 201):
        members = api_list_members(admin, cid)
        found = [m for m in members if m["username"] == new_user]
        assert found, "imported user not found"

        # must NOT become teacher
        assert found[0]["role_in_course"] != "teacher", found[0]
        # optionally assert it becomes student
        assert found[0]["role_in_course"] == "student", found[0]
    else:
        assert r.status_code in (401, 403), r.text