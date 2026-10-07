import requests
from _util import BASE_URL, rand, api_login, api_register, api_create_course, api_add_member

def test_FR_CSV1_export_members_neutralizes_formula_cells():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course = api_create_course(admin, title="c_"+rand(), description="d")
    cid = course["course_id"]

    # create user with formula-like display name
    u = requests.Session()
    uname = "u_"+rand()
    api_register(u, uname, "pw12345", "=1+1")

    # lookup user_id as admin
    r_users = admin.get(f"{BASE_URL}/api/users")
    assert r_users.status_code == 200
    users = r_users.json().get("users", [])
    uid = next((int(x["user_id"]) for x in users if x.get("username") == uname), None)
    assert uid is not None, "new user not found in /api/users"

    # add as member
    api_add_member(admin, cid, uid, "student")

    # NOTE: route aligned to your codebase (no .csv)
    r = admin.get(f"{BASE_URL}/api/courses/{cid}/members/export")
    assert r.status_code == 200
    text = r.text

    # Secure expectation: any cell starting with =,+,-,@ should be prefixed (e.g., with a quote)
    assert ",=1+1" not in text, text