import io, json, random, string, zipfile
from datetime import datetime, timezone
import requests
from conftest import BASE_URL

def rand(prefix="x"):
    return prefix + "_" + "".join(random.choice(string.ascii_lowercase + string.digits) for _ in range(10))

def make_zip_bytes(files: dict[str, bytes]) -> bytes:
    bio = io.BytesIO()
    with zipfile.ZipFile(bio, "w", compression=zipfile.ZIP_DEFLATED) as z:
        for name, content in files.items():
            z.writestr(name, content)
    return bio.getvalue()

def _assert_no_redirect(r: requests.Response):
    if r.status_code in (301,302,303,307,308):
        loc = r.headers.get("Location","")
        raise AssertionError(f"Unexpected redirect {r.status_code} Location={loc}")

def _extract_json_object(text: str) -> str:
    """
    PHP with display_errors=On may prepend HTML warnings before JSON.
    Extract the first JSON object that starts with '{"' (more reliable than first '{').
    """
    if not text:
        return text
    t = text.lstrip()

    start = t.find('{"')
    if start == -1:
        start = t.find('{')
    if start == -1:
        return t

    depth = 0
    in_str = False
    esc = False
    end = None
    for i in range(start, len(t)):
        ch = t[i]
        if in_str:
            if esc:
                esc = False
            elif ch == '\\':
                esc = True
            elif ch == '"':
                in_str = False
            continue
        else:
            if ch == '"':
                in_str = True
                continue
            if ch == '{':
                depth += 1
            elif ch == '}':
                depth -= 1
                if depth == 0:
                    end = i + 1
                    break
    if end is None:
        return t[start:]
    return t[start:end]

def json_or_fail(r: requests.Response):
    _assert_no_redirect(r)
    body = _extract_json_object(r.text or "")
    try:
        return json.loads(body)
    except Exception as e:
        ct = r.headers.get("Content-Type","")
        snippet = (r.text or "")[:600].replace("\n","\\n")
        raise AssertionError(
            f"Expected JSON but parse failed: {e}. ct={ct} status={r.status_code} body_snippet={snippet}"
        )

def api_register(s: requests.Session, username: str, password: str, display_name: str):
    r = s.post(BASE_URL + "/api/auth/register",
               json={"username": username, "password": password, "display_name": display_name},
               allow_redirects=False)
    assert r.status_code in (200,201), r.text
    return json_or_fail(r)

def api_login(s: requests.Session, username: str, password: str):
    r = s.post(BASE_URL + "/api/auth/login",
               json={"username": username, "password": password},
               allow_redirects=False)
    assert r.status_code == 200, r.text
    return json_or_fail(r)

def api_users(s: requests.Session):
    r = s.get(BASE_URL + "/api/users", allow_redirects=False)
    assert r.status_code == 200, r.text
    return json_or_fail(r)["users"]

def find_user_id(users, username: str):
    for u in users:
        if u.get("username") == username:
            return int(u["user_id"])
    return None

def api_create_course(s: requests.Session, title: str, description: str) -> int:
    r = s.post(BASE_URL + "/api/courses",
               json={"title": title, "description": description},
               allow_redirects=False)
    assert r.status_code in (200,201), r.text
    return int(json_or_fail(r)["course"]["course_id"])

def api_add_member(s: requests.Session, course_id: int, user_id: int, role: str):
    r = s.post(f"{BASE_URL}/api/courses/{course_id}/members",
               json={"user_id": user_id, "role_in_course": role},
               allow_redirects=False)
    assert r.status_code in (200,201), r.text
    return json_or_fail(r)

def api_create_assignment(s: requests.Session, course_id: int, title: str, description: str) -> int:
    due_at = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    r = s.post(f"{BASE_URL}/api/courses/{course_id}/assignments",
               json={"title": title, "description": description, "due_at": due_at},
               allow_redirects=False)
    assert r.status_code in (200,201), r.text
    return int(json_or_fail(r)["assignment"]["assignment_id"])

def api_create_submission(s: requests.Session, course_id: int, assignment_id: int, content_text: str):
    r = s.post(f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions",
               json={"content_text": content_text},
               allow_redirects=False)
    assert r.status_code in (200,201), r.text
    return json_or_fail(r)
