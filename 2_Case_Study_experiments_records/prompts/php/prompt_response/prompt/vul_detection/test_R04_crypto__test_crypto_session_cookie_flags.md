
You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.

### 2) Function Feature (security property)
Secure session cookie configuration: after login, the server must set the 
session cookie with HttpOnly and a valid SameSite attribute (Lax/Strict/None) to reduce XSS/CSRF risk.


### 3) Failed Security Test
import re
import pytest
import requests
from common import BASE_URL


def _all_set_cookie_headers(resp: requests.Response) -> list[str]:
    """
    Return all Set-Cookie header lines (not merged).
    requests may expose only one via resp.headers; raw headers are more reliable.
    """
    raw = getattr(resp, "raw", None)
    if raw and getattr(raw, "headers", None):
        h = raw.headers
        # urllib3 / http.client compatible
        if hasattr(h, "get_all"):
            vals = h.get_all("Set-Cookie") or []
            return [v for v in vals if v]
    # fallback (may be merged/partial)
    v = resp.headers.get("Set-Cookie")
    return [v] if v else []


def _pick_session_cookie(set_cookie_lines: list[str]) -> str | None:
    """
    Pick the session cookie line. Default PHP session cookie is PHPSESSID.
    Allow override via env if needed.
    """
    import os
    name = os.getenv("SESSION_COOKIE_NAME", "PHPSESSID")
    for line in set_cookie_lines:
        if line.lower().startswith(name.lower() + "="):
            return line
    # fallback: any cookie that looks like a session id (best effort)
    for line in set_cookie_lines:
        if re.match(r"^[A-Za-z0-9_]+=[^;]+;", line):
            if "phpsessid" in line.lower():
                return line
    return None


def test_session_cookie_has_httponly_and_samesite():
    s = requests.Session()
    r = s.post(
        f"{BASE_URL}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=10,
    )
    assert r.status_code == 200, r.text

    set_cookies = _all_set_cookie_headers(r)
    if not set_cookies:
        pytest.skip("Cannot observe Set-Cookie headers in this environment; skip cookie-flag validation.")

    sess_cookie = _pick_session_cookie(set_cookies)
    if not sess_cookie:
        pytest.skip(f"No recognizable session cookie found in Set-Cookie headers: {set_cookies}")

    lower = sess_cookie.lower()

    # Must have HttpOnly
    assert "httponly" in lower, f"Session cookie must include HttpOnly. Got: {sess_cookie}"

    # Must have SameSite
    m = re.search(r"samesite=([a-z]+)", lower)
    assert m, (
        "Session cookie should include SameSite (Lax/Strict/None). "
        f"Got: {sess_cookie}"
    )

    samesite = m.group(1)
    assert samesite in ("lax", "strict", "none"), f"Unexpected SameSite value: {samesite}. Full cookie: {sess_cookie}"

    # If SameSite=None, Secure is strongly recommended; do not hard-fail for local HTTP,
    # but warn by failing only if you want to enforce strict policy.
    if samesite == "none" and "secure" not in lower:
        pytest.skip("SameSite=None without Secure observed; in production this should be Secure. Skipping for local HTTP.")



You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.

### 2) Function Feature (security property)

Robust error handling: the API must reject malformed JSON with a 4xx response
(e.g., 400/415/422) and must not leak stack traces, file paths, DB errors, or secrets in the error body.


### 3) Failed Security Test
import re

# ============================================================
# API (JSON) - Mishandling Exceptional Conditions
# ============================================================

def _text(rv):
try:
return rv.get_data(as_text=True)
except Exception:
return (rv.data or b"").decode("utf-8", errors="ignore")


def _no_leak(text):
forbidden = [
r"Traceback", r"Werkzeug", r"Debugger",
r"File \"[^\"]+\.py\"", r"/[A-Za-z0-9_\-./]+\.py", r"\\[A-Za-z0-9_\-\\.]+\.py",
r"sqlite3\.", r"OperationalError", r"SECRET_KEY",
]
for pat in forbidden:
assert re.search(pat, text, re.I) is None, f"Leakage matched /{pat}/"


def test_api_malformed_json_returns_4xx_and_no_leak(client, auth, sample_course):
# Ensure authenticated (some APIs require auth)
auth.register("exc_api_user", "pass", "Exc API User")
auth.login("exc_api_user", "pass")

    # Choose a common JSON endpoint (course creation)
    rv = client.post(
        "/api/courses",
        data='{"title":',
        headers={"Content-Type": "application/json"},
        follow_redirects=False,
    )
    if rv.status_code == 404:
        # API not implemented in this variant
        return

    assert rv.status_code in (400, 415, 422), f"Expected 4xx for malformed JSON, got {rv.status_code}"
    _no_leak(_text(rv))

### 4) Output Requirements (STRICT)

Your response MUST contain exactly the following 6 sections, in this exact order.
Do NOT add extra sections.
Do NOT output any code diff.

4.0 VulType
Choose exactly ONE from: R01 R02 R03 R04 R05 R06 R07 R08 R09 R10
Format:
VulType: Rxx


4.1 RelatedFiles
List ONLY the concrete file names/paths that are involved in the failing request path (/api/courses).
If a required file is not shown in the provided snippets, write: MISSING: <expected file role>

Format:
RelatedFiles:
- <file_path_1>
- <file_path_2>
- MISSING: <role description>


4.2 RelevantCodeInsideFiles
For EACH file listed in 4.1:
- Identify the exact route / middleware / function related to GET /api/courses
- Quote ONLY the minimal relevant code fragment (no full file dump)

Format:
RelevantCodeInsideFiles:
File: <file_path>
- Route/Middleware/Function: <name>
- Relevant Code:
  <minimal code snippet>

If file is MISSING, write:
File: MISSING
- Needed: <what must be located (e.g., route definition for GET /api/courses)>


4.3 RootCause
2–4 bullets explaining:
- What check is missing or incorrect
- Exactly where it should be enforced (route level / middleware level / controller)

Format:
RootCause:
- ...
- ...


4.4 ActionablePlan
Concrete minimal repair steps.
Each bullet MUST include:
- Target File
- Target Function/Route
- What to change (precise behavior, e.g., “add auth middleware before handler” or “return 401 if no session.user”)

Format:
ActionablePlan:
- Target File: ...
  Target: ...
  Change: ...

- Target File: ...
  Target: ...
  Change: ...


4.5 FileToActionMap
Map files → repair actions clearly.

Format:
FileToActionMap:
- <file_path> → <short description of modification>
- <file_path> → <short description of modification>