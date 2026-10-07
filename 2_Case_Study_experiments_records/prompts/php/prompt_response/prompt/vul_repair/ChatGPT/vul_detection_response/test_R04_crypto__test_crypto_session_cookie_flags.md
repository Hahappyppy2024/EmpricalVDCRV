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


### 4.0 VulType

VulType: R02

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: Auth API route/controller that handles `POST /api/auth/login` (sets the session cookie)
* MISSING: Session configuration / app bootstrap where cookie flags are configured (e.g., Flask app factory `app.py` / `__init__.py`)
* MISSING: Any middleware/session library initialization (Flask session / custom session) that actually emits `Set-Cookie`

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: login handler for `POST /api/auth/login` showing where the session is established (e.g., `session["user_id"]=...`) and where/if cookie attributes are set.

File: MISSING

* Needed: app/session configuration code that determines cookie flags (e.g., `SESSION_COOKIE_HTTPONLY`, `SESSION_COOKIE_SAMESITE`, `SESSION_COOKIE_SECURE`, cookie name).

File: MISSING

* Needed: any code that overrides cookie headers manually (could be missing `HttpOnly`/`SameSite` or using an invalid SameSite value).

---

### 4.3 RootCause

RootCause:

* The login response sets a session cookie (test can see `Set-Cookie`) but the cookie line likely **omits `HttpOnly`** and/or **omits `SameSite=`**, causing the assertions to fail.
* Alternatively, the app may set `SameSite` to a non-standard value (not `Lax|Strict|None`), which fails the strict value check.
* The correct enforcement point is the **global session cookie configuration** (applies to all responses that set the session cookie) rather than per-route ad-hoc header strings.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Session/app configuration (bootstrap/app factory)** (MISSING)
  Target: session cookie settings used by the framework/session mechanism
  Change: Configure the session cookie to include:

    * `HttpOnly` enabled
    * `SameSite` set to a valid value (`Lax` recommended for typical web apps; `Strict` is tighter; `None` requires `Secure` in real HTTPS)
      Ensure these settings apply to the cookie name used by the app.

* Target File: **Auth login controller** (MISSING)
  Target: `POST /api/auth/login`
  Change: Ensure login establishes a server-side session (e.g., sets session user identifier) so the framework emits `Set-Cookie` with the configured flags. Do not manually craft `Set-Cookie` without flags; rely on configured session system or ensure manual header includes both `HttpOnly` and `SameSite=<valid>`.

* Target File: **Cookie emission override (if present)** (MISSING)
  Target: any custom response wrapper that sets cookies
  Change: If cookies are set manually, update the cookie set call to include `httponly=True` and `samesite="Lax"` (or `Strict/None`) and avoid invalid values/casing.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: app bootstrap/session config → set session cookie flags globally: HttpOnly + SameSite (valid)
* MISSING: `POST /api/auth/login` handler → ensure session is created so cookie is issued with the configured flags
* MISSING: any manual cookie-setting code → include `HttpOnly` and `SameSite=<Lax|Strict|None>` (avoid invalid SameSite)


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