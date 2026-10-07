You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


### 2) Function Feature (security property)
Session fixation mitigation: the application must regenerate
(rotate) the session ID after a successful login so the pre-login session identifier cannot be reused.


### 3) Failed Security Test
import os
import re
import pytest
import requests

from common import BASE_URL


SESSION_COOKIE_NAME = os.getenv("SESSION_COOKIE_NAME", "PHPSESSID")


def _all_set_cookie_headers(resp: requests.Response) -> list[str]:
raw = getattr(resp, "raw", None)
if raw and getattr(raw, "headers", None) and hasattr(raw.headers, "get_all"):
return raw.headers.get_all("Set-Cookie") or []
v = resp.headers.get("Set-Cookie")
return [v] if v else []


def _extract_cookie_value_from_set_cookie(set_cookie_line: str, name: str) -> str | None:
# e.g., "PHPSESSID=abc; path=/; HttpOnly; SameSite=Lax"
m = re.match(rf"^{re.escape(name)}=([^;]*)", set_cookie_line, flags=re.IGNORECASE)
return m.group(1) if m else None


def _get_session_id_from_jar(s: requests.Session) -> str | None:
v = s.cookies.get(SESSION_COOKIE_NAME)
return v if v else None


def _ensure_prelogin_session(s: requests.Session) -> str | None:
"""
Try a couple of endpoints to force session creation.
Return session id if obtained, else None.
"""
# 1) HTML login page often initializes session
s.get(f"{BASE_URL}/login", timeout=10, allow_redirects=False)
sid = _get_session_id_from_jar(s)
if sid:
return sid

    # 2) an API GET may also set a session cookie
    s.get(f"{BASE_URL}/api/auth/me", timeout=10, allow_redirects=False)
    sid = _get_session_id_from_jar(s)
    return sid


def _assert_logged_in(s: requests.Session):
r = s.get(f"{BASE_URL}/api/auth/me", timeout=10, allow_redirects=False)
# Some apps use 200 with JSON; others might use 401 when not logged in.
assert r.status_code == 200, f"Login did not establish session. /api/auth/me -> {r.status_code} body={r.text[:200]}"
try:
data = r.json()
except Exception:
pytest.fail(f"/api/auth/me did not return JSON. body_head={r.text[:200]}")
assert "user" in data, f"/api/auth/me JSON missing user. keys={list(data.keys())}"


def test_login_regenerates_session_id():
s = requests.Session()

    sid_before = _ensure_prelogin_session(s)
    if not sid_before:
        pytest.skip(
            f"No {SESSION_COOKIE_NAME} cookie was set before login; cannot test fixation in a black-box way. "
            "Ensure the app initializes a session on first visit or expose a pre-login endpoint that sets it."
        )

    # Perform login
    r1 = s.post(
        f"{BASE_URL}/api/auth/login",
        json={"username": "admin", "password": "admin123"},
        timeout=10,
        allow_redirects=False,
    )
    assert r1.status_code == 200, r1.text[:200]

    # Confirm login actually succeeded (avoid false pass where cookie changes but auth fails)
    _assert_logged_in(s)

    # Read session id after login (from cookie jar)
    sid_after = _get_session_id_from_jar(s)
    assert sid_after, f"Post-login {SESSION_COOKIE_NAME} cookie missing."

    # Also try to read the newly-set session id from Set-Cookie (stronger signal)
    set_cookies = _all_set_cookie_headers(r1)
    sid_after_setcookie = None
    for line in set_cookies:
        v = _extract_cookie_value_from_set_cookie(line, SESSION_COOKIE_NAME)
        if v is not None:
            sid_after_setcookie = v
            break

    # Primary assertion: session id must change after login
    assert sid_after != sid_before, (
        f"Session id did not change after login (cookie {SESSION_COOKIE_NAME}). "
        "Mitigation: call session_regenerate_id(true) after successful login."
    )

    # Secondary consistency check (if we observed Set-Cookie)
    if sid_after_setcookie is not None:
        assert sid_after_setcookie == sid_after, (
            f"Mismatch between Set-Cookie {SESSION_COOKIE_NAME} and cookie jar. "
            f"setcookie={sid_after_setcookie!r} jar={sid_after!r}"
        )




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


4.0 VulType

VulType: R07



4.1 RelatedFiles

\- MISSING: Route handler for POST /api/auth/login (or /login)

\- MISSING: Session management configuration or authentication library logic



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Logic that processes the login request and establishes the user session



4.3 RootCause

\- The application fails to regenerate the session identifier upon successful authentication.

\- It continues to use the session ID provided in the request before login, allowing an attacker to fixate a session ID and hijack the user's account after they log in.



4.4 ActionablePlan

\- Target File: routes/auth.py (or the controller handling login)

&nbsp; Target: Login handler function

&nbsp; Change: Immediately after verifying credentials and before setting the user as logged in, call the framework's session regeneration function (e.g., `session.regenerate()` or `session.clear()` followed by setting new session data) to issue a new session ID.



4.5 FileToActionMap

\- routes/auth.py → Add session ID regeneration step to the login process.



Output Requirements (STRICT):
Your response MUST contain exactly these 3 sections, in this exact order.

[1] Patch
Output EXACTLY ONE unified diff (git diff style). No alternative patches.

[2] Evidence Mapping
Provide 2–4 bullet lines in the form:
- Signal: <failing test / finding> -> Location: <file:function/route> -> Fix: <<=12 words>

[3] FailureModeGuess
failure_mode_guess: "<Regression|ContractDrift|WrongLocalization|NotFixed|BudgetExceeded|NewSecIssue|N/A>"
failure_mode_guess_reason: "<one sentence tied to signals/patch scope>"