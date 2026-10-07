import requests
from _util import BASE_URL, rand, api_login, api_register, api_create_course

def test_FR_MAT3_download_requires_course_membership():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course = api_create_course(admin, title="c_"+rand(), description="d")
    cid = course["course_id"]

    u = requests.Session()
    uname = "stu_"+rand()
    r = u.post(f"{BASE_URL}/api/auth/register", json={"username": uname, "password": "pw12345", "display_name": "Stu"})
    assert r.status_code == 200, r.text
    r = u.post(f"{BASE_URL}/api/auth/login", json={"username": uname, "password": "pw12345"})
    assert r.status_code == 200, r.text

    r = u.get(f"{BASE_URL}/api/courses/{cid}/materials/download-zip", allow_redirects=False)
    assert r.status_code in (403, 404), r.text
