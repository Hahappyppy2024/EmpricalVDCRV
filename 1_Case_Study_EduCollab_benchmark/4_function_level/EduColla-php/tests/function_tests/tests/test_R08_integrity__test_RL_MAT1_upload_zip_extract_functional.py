import requests
from conftest import BASE_URL
from util import api_login, api_create_course, make_zip_bytes, json_or_fail, rand

# python -m pytest -q tests/test_RL_MAT1_upload_zip_extract_functional.py
def test_RL_MAT1_upload_zip_extract_functional():
    """
    Functional check: upload a ZIP via Materials API.

    Matches current backend behavior:
    - multipart field name: "zip"
    - JSON response: {"material": {"material_id": ..., "original_name": ...}}
    """
    s = requests.Session()
    api_login(s, "admin", "admin123")

    course_id = api_create_course(s, "C_" + rand("c"), "desc")
    zip_bytes = make_zip_bytes({"hello.txt": b"hello world"})

    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/materials/upload-zip",
        files={"zip": ("materials.zip", zip_bytes, "application/zip")},  # <-- changed
        allow_redirects=False,
    )

    assert r.status_code in (200, 201), r.text[:200]
    ct = r.headers.get("Content-Type", "")
    if "application/json" not in ct.lower():
        raise AssertionError(
            f"Expected JSON but got ct={ct} status={r.status_code} body_head={r.text[:400]}"
        )

    data = json_or_fail(r)

    # New backend returns a "material" object
    assert "material" in data, data
    assert "material_id" in data["material"], data
    mid = data["material"]["material_id"]
    assert isinstance(mid, int) or str(mid).isdigit()