import requests, io, zipfile
from conftest import BASE_URL
from util import api_login, api_create_course, make_zip_bytes, rand

def test_RL_MAT3_download_materials_zip_functional():
    s = requests.Session()
    api_login(s, "admin", "admin123")
    course_id = api_create_course(s, "C_" + rand("c"), "desc")
    zip_bytes = make_zip_bytes({"hello.txt": b"hello"})
    r_up = s.post(f"{BASE_URL}/api/courses/{course_id}/materials/upload-zip",
                  files={"zip": ("m.zip", zip_bytes, "application/zip")},
                  allow_redirects=False)
    assert r_up.status_code in (200,201), r_up.text[:200]
    r = s.get(f"{BASE_URL}/api/courses/{course_id}/materials/download-zip", allow_redirects=False)
    assert r.status_code == 200, (r.text or "")[:200]
    assert "zip" in r.headers.get("Content-Type","")
    z = zipfile.ZipFile(io.BytesIO(r.content))
    assert any(n.endswith("hello.txt") for n in z.namelist())
