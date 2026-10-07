import os, json, random, string, io, zipfile
import requests

BASE_URL = os.environ.get("BASE_URL", "http://localhost:8000").rstrip("/")

def rand(suffix=""):
    return ''.join(random.choice(string.ascii_lowercase+string.digits) for _ in range(8)) + suffix

def api_login(session: requests.Session, username: str, password: str):
    r = session.post(f"{BASE_URL}/api/auth/login", json={"username": username, "password": password})
    assert r.status_code == 200, r.text
    return r.json()

def api_register(session: requests.Session, username: str, password: str, display_name: str):
    r = session.post(f"{BASE_URL}/api/auth/register", json={"username": username, "password": password, "display_name": display_name})
    assert r.status_code == 200, r.text
    return r.json()

def api_create_course(session: requests.Session, title="t", description="d"):
    r = session.post(f"{BASE_URL}/api/courses", json={"title": title, "description": description})
    assert r.status_code == 200, r.text
    return r.json()["course"]

def api_add_member(session: requests.Session, course_id: int, user_id: int, role: str):
    r = session.post(f"{BASE_URL}/api/courses/{course_id}/members", json={"user_id": user_id, "role_in_course": role})
    assert r.status_code == 200, r.text
    return r.json()["membership"]

def api_list_members(session: requests.Session, course_id: int):
    r = session.get(f"{BASE_URL}/api/courses/{course_id}/members")
    assert r.status_code == 200, r.text
    return r.json()["members"]

def make_zip_with_entry(name: str, data: bytes):
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", compression=zipfile.ZIP_DEFLATED) as z:
        z.writestr(name, data)
    buf.seek(0)
    return buf
