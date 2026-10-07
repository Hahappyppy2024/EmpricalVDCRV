import os
import uuid
import requests

BASE_URL = os.getenv("BASE_URL", "http://localhost:8000").rstrip("/")


# -----------------------
# Utilities
# -----------------------
def new_username(prefix: str) -> str:
    return f"{prefix}_{uuid.uuid4().hex[:10]}"


def html_get(session: requests.Session, path: str) -> str:
    """
    GET an HTML route and return HTML text.
    Fails if response != 200.
    """
    r = session.get(f"{BASE_URL}{path}", timeout=10)
    assert r.status_code == 200, r.text
    return r.text


def html_get_raw(session: requests.Session, path: str, allow_redirects: bool = False) -> requests.Response:
    """
    GET an HTML route and return the raw Response for status/header assertions.
    Default: do NOT follow redirects to allow testing 302 -> /login behavior.
    """
    return session.get(f"{BASE_URL}{path}", timeout=10, allow_redirects=allow_redirects)


def html_post_form_raw(session: requests.Session, path: str, data: dict, allow_redirects: bool = False) -> requests.Response:
    """
    POST an HTML form (application/x-www-form-urlencoded) and return raw Response.
    Default: do NOT follow redirects to allow checking 302 and Location.
    """
    return session.post(f"{BASE_URL}{path}", data=data, timeout=10, allow_redirects=allow_redirects)


# -----------------------
# API helpers (to setup data quickly)
# -----------------------
def api_login_admin() -> requests.Session:
    """
    Test setup: login as the seeded admin user via API to obtain a session cookie.
    What it tests: /api/auth/login authenticates and sets session cookie.
    """
    s = requests.Session()
    r = s.post(f"{BASE_URL}/api/auth/login", json={"username": "admin", "password": "admin123"}, timeout=10)
    assert r.status_code == 200, r.text
    return s


def api_register_and_login_student() -> tuple[requests.Session, dict]:
    """
    Test setup: register + login a fresh student via API.
    What it tests: /api/auth/register and /api/auth/login work and produce a usable session.
    """
    username = new_username("stu")
    password = "pass1234"
    display_name = "Student"

    s = requests.Session()
    r = s.post(
        f"{BASE_URL}/api/auth/register",
        json={"username": username, "password": password, "display_name": display_name},
        timeout=10,
    )
    assert r.status_code == 200, r.text

    r = s.post(f"{BASE_URL}/api/auth/login", json={"username": username, "password": password}, timeout=10)
    assert r.status_code == 200, r.text

    me = s.get(f"{BASE_URL}/api/auth/me", timeout=10).json()["user"]
    return s, me


def api_create_course(admin_s: requests.Session, title: str = "Course", description: str = "") -> dict:
    """
    Test setup: create a course via API.
    """
    r = admin_s.post(f"{BASE_URL}/api/courses", json={"title": title, "description": description}, timeout=10)
    assert r.status_code == 200, r.text
    return r.json()["course"]


def api_add_member(admin_s: requests.Session, course_id: int, user_id: int, role: str) -> dict:
    """
    Test setup: add a member to course via API.
    """
    r = admin_s.post(
        f"{BASE_URL}/api/courses/{course_id}/members",
        json={"user_id": user_id, "role_in_course": role},
        timeout=10,
    )
    assert r.status_code == 200, r.text
    return r.json()["membership"]


def api_create_post(s: requests.Session, course_id: int, title: str, body: str) -> dict:
    """
    Test setup: create a post via API.
    """
    r = s.post(f"{BASE_URL}/api/courses/{course_id}/posts", json={"title": title, "body": body}, timeout=10)
    assert r.status_code == 200, r.text
    return r.json()["post"]


def api_create_comment(s: requests.Session, course_id: int, post_id: int, body: str) -> dict:
    """
    Test setup: create a comment via API.
    """
    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/posts/{post_id}/comments",
        json={"body": body},
        timeout=10,
    )
    assert r.status_code == 200, r.text
    return r.json()["comment"]


def api_create_assignment(staff_s: requests.Session, course_id: int, title: str, description: str = "", due_at: str = "") -> dict:
    """
    Test setup: create an assignment via API.
    """
    r = staff_s.post(
        f"{BASE_URL}/api/courses/{course_id}/assignments",
        json={"title": title, "description": description, "due_at": due_at},
        timeout=10,
    )
    assert r.status_code == 200, r.text
    return r.json()["assignment"]


def api_submit_assignment(
    student_s: requests.Session,
    course_id: int,
    assignment_id: int,
    content_text: str,
    attachment_upload_id=None,
) -> dict:
    """
    Test setup: submit an assignment via API.
    """
    payload = {"content_text": content_text}
    if attachment_upload_id is not None:
        payload["attachment_upload_id"] = attachment_upload_id

    r = student_s.post(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions",
        json=payload,
        timeout=10,
    )
    assert r.status_code == 200, r.text
    return r.json()["submission"]