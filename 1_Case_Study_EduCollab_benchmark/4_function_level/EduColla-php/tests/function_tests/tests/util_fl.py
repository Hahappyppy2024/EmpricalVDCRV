import io
import random
import string
import zipfile
from datetime import datetime, timezone

import requests
from conftest import BASE_URL

import json
# ----------------------------
# Helpers
# ----------------------------
def rand(prefix: str = "x") -> str:
    return prefix + "_" + "".join(random.choice(string.ascii_lowercase + string.digits) for _ in range(10))


def now_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def make_zip_bytes(files: dict[str, bytes]) -> bytes:
    bio = io.BytesIO()
    with zipfile.ZipFile(bio, "w", compression=zipfile.ZIP_DEFLATED) as z:
        for name, content in files.items():
            z.writestr(name, content)
    return bio.getvalue()


def json_or_fail(r: requests.Response) -> dict:
    """
    Function-level tests should prefer JSON endpoints.
    If server accidentally returns HTML (e.g., warnings), this will raise with a helpful snippet.
    """
    ct = r.headers.get("Content-Type", "")
    try:
        return r.json()
    except Exception:
        snippet = (r.text or "")[:800].replace("\n", "\\n")
        raise AssertionError(f"Expected JSON but got ct={ct} status={r.status_code} body_snippet={snippet}")


def api_login(s: requests.Session, username: str, password: str) -> dict:
    r = s.post(BASE_URL + "/api/auth/login", json={"username": username, "password": password}, allow_redirects=False)
    assert r.status_code == 200, f"login failed status={r.status_code} body={r.text[:500]}"
    return json_or_fail(r)


def api_register(s: requests.Session, username: str, password: str, display_name: str) -> dict:
    r = s.post(
        BASE_URL + "/api/auth/register",
        json={"username": username, "password": password, "display_name": display_name},
        allow_redirects=False,
    )
    assert r.status_code in (200, 201), f"register failed status={r.status_code} body={r.text[:500]}"
    return json_or_fail(r)


def api_users(s: requests.Session) -> list[dict]:
    r = s.get(BASE_URL + "/api/users", allow_redirects=False)
    assert r.status_code == 200, f"users failed status={r.status_code} body={r.text[:500]}"
    return json_or_fail(r).get("users", [])


def find_user_id_by_username(users: list[dict], username: str) -> int | None:
    for u in users:
        if u.get("username") == username:
            return int(u["user_id"])
    return None


def api_create_course(s: requests.Session, title: str, description: str) -> int:
    r = s.post(BASE_URL + "/api/courses", json={"title": title, "description": description}, allow_redirects=False)
    assert r.status_code in (200, 201), f"create_course failed status={r.status_code} body={r.text[:500]}"
    return int(json_or_fail(r)["course"]["course_id"])


def api_add_member(s: requests.Session, course_id: int, user_id: int, role: str) -> dict:
    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/members",
        json={"user_id": user_id, "role_in_course": role},
        allow_redirects=False,
    )
    assert r.status_code in (200, 201), f"add_member failed status={r.status_code} body={r.text[:500]}"
    return json_or_fail(r)


def api_create_assignment(s: requests.Session, course_id: int, title: str, description: str) -> int:
    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/assignments",
        json={"title": title, "description": description, "due_at": now_iso()},
        allow_redirects=False,
    )
    assert r.status_code in (200, 201), f"create_assignment failed status={r.status_code} body={r.text[:500]}"
    return int(json_or_fail(r)["assignment"]["assignment_id"])


def api_upload_file(
    s: requests.Session,
    course_id: int,
    filename: str,
    content: bytes,
    content_type: str = "application/octet-stream",
) -> int:
    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/uploads",
        files={"file": (filename, content, content_type)},
        allow_redirects=False,
    )
    assert r.status_code in (200, 201), f"upload failed status={r.status_code} body={r.text[:500]}"
    return int(json_or_fail(r)["upload"]["upload_id"])


def api_create_submission(
    s: requests.Session,
    course_id: int,
    assignment_id: int,
    content_text: str,
    attachment_upload_id: int | None = None,
) -> int:
    payload = {"content_text": content_text}
    if attachment_upload_id is not None:
        payload["attachment_upload_id"] = attachment_upload_id

    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions",
        json=payload,
        allow_redirects=False,
    )
    assert r.status_code in (200, 201), f"create_submission failed status={r.status_code} body={r.text[:500]}"
    return int(json_or_fail(r)["submission"]["submission_id"])


# ----------------------------
# Function-level endpoints (v1)
# ----------------------------
def fl_unzip_submission(s: requests.Session, course_id: int, assignment_id: int, submission_id: int) -> dict:
    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}/unzip",
        allow_redirects=False,
    )
    assert r.status_code in (200, 201), f"unzip failed status={r.status_code} body={r.text[:800]}"
    return json_or_fail(r)


def fl_list_submission_files(s: requests.Session, course_id: int, assignment_id: int, submission_id: int) -> dict:
    r = s.get(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}/files",
        allow_redirects=False,
    )
    assert r.status_code == 200, f"list_files failed status={r.status_code} body={r.text[:800]}"
    return json_or_fail(r)


def fl_export_assignment_submissions_zip(s: requests.Session, course_id: int, assignment_id: int) -> dict:
    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/export-zip",
        allow_redirects=False,
    )
    assert r.status_code in (200, 201), f"export_zip failed status={r.status_code} body={r.text[:800]}"
    return json_or_fail(r)


def fl_download_submissions_zip(s: requests.Session, course_id: int, assignment_id: int, job_id: str) -> requests.Response:
    # NOTE: your index.php route ends with /download
    r = s.get(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/export-zip/{job_id}/download",
        allow_redirects=False,
    )
    assert r.status_code == 200, f"download_zip failed status={r.status_code} body={r.text[:300]}"
    return r


def fl_export_grades_csv(s: requests.Session, course_id: int, assignment_id: int) -> requests.Response:
    # Your function_tests util previously used grades.csv; keep it here if your function-level app still exposes it.
    r = s.get(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/grades.csv",
        allow_redirects=False,
    )
    assert r.status_code == 200, f"export_grades failed status={r.status_code} body={r.text[:300]}"
    return r


def fl_import_grades_csv(s: requests.Session, course_id: int, assignment_id: int, csv_bytes: bytes) -> dict:
    # Field name may differ across versions; your old util used csv_file.
    r = s.post(
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/grades/import",
        files={"csv_file": ("grades.csv", csv_bytes, "text/csv")},
        allow_redirects=False,
    )
    assert r.status_code in (200, 201), f"import_grades failed status={r.status_code} body={r.text[:800]}"
    return json_or_fail(r)


def fl_download_export_zip(s: requests.Session, course_id: int, assignment_id: int, job_id: str) -> requests.Response:
    # Backward-compatible alias for older tests
    return fl_download_submissions_zip(s, course_id, assignment_id, job_id)


#  def fl_download_export_zip(s: requests.Session, course_id: int, assignment_id: int, job_id):
#      # Backward-compatible: allow dict {"job_id": "..."} or plain string
#      if isinstance(job_id, dict):
#          job_id = job_id.get("job_id")
#      return fl_download_submissions_zip(s, course_id, assignment_id, job_id)


def _extract_json_object(text: str) -> str:
    """
    PHP display_errors=On may prepend HTML warnings before JSON.
    Extract the first JSON object starting at '{"' and return the matched {...}.
    """
    if not text:
        return text
    t = text.lstrip()

    start = t.find('{"')
    if start == -1:
        start = t.find("{")
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
            elif ch == "\\":
                esc = True
            elif ch == '"':
                in_str = False
            continue
        else:
            if ch == '"':
                in_str = True
                continue
            if ch == "{":
                depth += 1
            elif ch == "}":
                depth -= 1
                if depth == 0:
                    end = i + 1
                    break

    if end is None:
        return t[start:]
    return t[start:end]


def json_or_fail(r: requests.Response) -> dict:
    ct = r.headers.get("Content-Type", "")
    body = _extract_json_object(r.text or "")
    try:
        return json.loads(body)
    except Exception as e:
        snippet = (r.text or "")[:900].replace("\n", "\\n")
        raise AssertionError(
            f"Expected JSON but parse failed: {e}. ct={ct} status={r.status_code} body_snippet={snippet}"
        )

def fl_export_grades_csv(s, course_id, assignment_id):
    r = s.get(f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/grades/export", allow_redirects=False)
    assert r.status_code == 200, r.text[:300]
    return r