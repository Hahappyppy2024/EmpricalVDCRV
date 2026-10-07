import requests
from _util import BASE_URL, rand, api_login, api_create_course, make_zip_with_entry

def test_FR_MAT1_rejects_traversal_entries():
    s = requests.Session()
    api_login(s, "admin", "admin123")
    course = api_create_course(s, title="c_"+rand(), description="d")
    cid = course["course_id"]

    # A traversal entry that would attempt to escape the extract directory
    zbuf = make_zip_with_entry("../../../../pwned.txt", b"pwned")
    files = {"zip_file": ("evil.zip", zbuf.read(), "application/zip")}

    r = s.post(f"{BASE_URL}/api/courses/{cid}/materials/upload-zip", files=files)

    # Secure expectation: server should reject the ZIP
    assert r.status_code in (400, 422), r.text
