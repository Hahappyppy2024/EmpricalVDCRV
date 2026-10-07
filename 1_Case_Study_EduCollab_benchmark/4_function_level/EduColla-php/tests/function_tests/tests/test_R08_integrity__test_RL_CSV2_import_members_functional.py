import io
import requests
from conftest import BASE_URL
from util import api_login, api_create_course, rand

def test_RL_CSV2_import_members_functional():
    admin = requests.Session()
    api_login(admin, "admin", "admin123")
    course_id = api_create_course(admin, "C_" + rand("c"), "desc")

    csv_content = (
        "username,password,display_name,role_in_course\n"
        f"{rand('stu')},pw12345,Student One,student\n"
        f"{rand('ta')},pw12345,TA One,assistant\n"
    ).encode("utf-8")

    url = f"{BASE_URL}/api/courses/{course_id}/members/import.csv"

    # Try multiple common field names in one request (backend may expect different key)
    files = [
        ("csv", ("members.csv", io.BytesIO(csv_content), "text/csv")),
        ("file", ("members.csv", io.BytesIO(csv_content), "text/csv")),
        ("csv_file", ("members.csv", io.BytesIO(csv_content), "text/csv")),
        ("upload", ("members.csv", io.BytesIO(csv_content), "text/csv")),
    ]

    r = admin.post(url, files=files, allow_redirects=False)

    assert r.status_code in (200, 201), (
        f"status={r.status_code} url={url} ct={r.headers.get('Content-Type')} body={r.text[:2000]}"
    )

    # If your endpoint returns JSON, validate minimal success
    ct = (r.headers.get("Content-Type") or "").lower()
    if "application/json" in ct:
        data = r.json()
        imp = data.get("imported", {}) if isinstance(data, dict) else {}
        assert isinstance(imp, dict), data
        assert imp.get("created_users", 0) + imp.get("added_memberships", 0) >= 1, data
    else:
        # Some variants might return plain text/CSV; at least ensure it's not an error page
        assert "upload error" not in (r.text or "").lower()