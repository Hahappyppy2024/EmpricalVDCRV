import requests
from _util import BASE_URL, rand, api_login, api_register, api_create_course, api_add_member

def test_FR_MAT2_list_requires_course_membership():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course = api_create_course(admin, title="c_"+rand(), description="d")
    cid = course["course_id"]

    # create a user but do NOT add to course
    u = requests.Session()
    uname = "stu_"+rand()
    api_register(u, uname, "pw12345", "Stu")
    api_login(u, uname, "pw12345")

    r = u.get(f"{BASE_URL}/api/courses/{cid}/materials/files")
    # Secure expectation: non-member shouldn't access course materials list
    assert r.status_code in (403, 404), r.text
