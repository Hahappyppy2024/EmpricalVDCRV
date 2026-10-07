You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


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


4.0 VulType

VulType: R05



4.1 RelatedFiles

\- MISSING: Main application entry file (e.g., app.py) or session configuration file

\- MISSING: Session middleware initialization code



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Session cookie configuration settings



4.3 RootCause

\- The session cookie configuration is missing the `HttpOnly` flag, leaving the cookie accessible to client-side scripts and vulnerable to XSS theft.

\- The session cookie configuration is missing the `SameSite` attribute, leaving the application vulnerable to Cross-Site Request Forgery (CSRF).



4.4 ActionablePlan

\- Target File: app.py (or config.py)

&nbsp; Target: Session configuration settings

&nbsp; Change: Set the session cookie options to include `HttpOnly=True` and `SameSite='Lax'` (or `'Strict'`). In Flask, this corresponds to `SESSION\_COOKIE\_HTTPONLY = True` and `SESSION\_COOKIE\_SAMESITE = 'Lax'`.



4.5 FileToActionMap

\- app.py → Update session cookie configuration to enforce HttpOnly and SameSite attributes.



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