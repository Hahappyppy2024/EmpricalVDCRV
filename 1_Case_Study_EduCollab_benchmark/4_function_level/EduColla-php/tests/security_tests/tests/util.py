import os, json, random, string, io, zipfile
import requests

BASE_URL = os.environ.get("BASE_URL", "http://localhost:8000").rstrip("/")

def rand(prefix="u"):
    return prefix + "_" + "".join(random.choice(string.ascii_lowercase+string.digits) for _ in range(8))

def api_register(username, password, display_name=None):
    r = requests.post(BASE_URL + "/api/auth/register", json={
        "username": username, "password": password, "display_name": display_name or username
    })
    return r

def api_login(username, password):
    s = requests.Session()
    r = s.post(BASE_URL + "/api/auth/login", json={"username": username, "password": password})
    return s, r

def api_create_course(sess, title="c", description="d"):
    r = sess.post(BASE_URL + "/api/courses", json={"title": title, "description": description})
    return r

def api_add_member(sess, course_id, user_id, role):
    r = sess.post(f"{BASE_URL}/api/courses/{course_id}/members", json={"user_id": user_id, "role_in_course": role})
    return r

def api_create_assignment(sess, course_id, title="a1"):
    r = sess.post(f"{BASE_URL}/api/courses/{course_id}/assignments", json={"title": title, "description": "d", "due_at": ""})
    return r

def api_upload_file(sess, course_id, filename, content: bytes):
    files = {"file": (filename, content)}
    r = sess.post(f"{BASE_URL}/api/courses/{course_id}/uploads", files=files)
    return r

def api_create_submission(sess, course_id, assignment_id, content_text="hello", attachment_upload_id=None):
    payload={"content_text": content_text}
    if attachment_upload_id is not None:
        payload["attachment_upload_id"]=attachment_upload_id
    r = sess.post(f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions", json=payload)
    return r

def make_zip_bytes(entries):
    bio=io.BytesIO()
    with zipfile.ZipFile(bio, "w", zipfile.ZIP_DEFLATED) as z:
        for path, data in entries:
            z.writestr(path, data)
    return bio.getvalue()
