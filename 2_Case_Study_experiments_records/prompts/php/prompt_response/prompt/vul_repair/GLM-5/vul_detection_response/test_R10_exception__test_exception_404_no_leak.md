You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


### 2) Function Feature (security property)

Secure file download responses: the upload download endpoint must serve files
as application/octet-stream and include X-Content-Type-Options: nosniff to prevent MIME sniffing.


### 3) Failed Security Test

import io
from common import api_login_admin, api_create_course, BASE_URL

def test_download_sets_nosniff_header():
admin = api_login_admin()
course = api_create_course(admin, title='nosniff-course')

    files = {'file': ('note.html', io.BytesIO(b'<h1>hi</h1>'), 'text/html')}
    r = admin.post(f"{BASE_URL}/api/courses/{course['course_id']}/uploads", files=files, timeout=10)
    assert r.status_code == 200, r.text
    up = r.json()['upload']

    r = admin.get(f"{BASE_URL}/api/courses/{course['course_id']}/uploads/{up['upload_id']}/download", timeout=10)
    assert r.status_code == 200

    # Content-Type is forced to octet-stream in the app; keep verifying it.
    assert r.headers.get('Content-Type', '').lower().startswith('application/octet-stream')

    # Recommended hardening header (missing => potential sniffing issues in some contexts).
    assert r.headers.get('X-Content-Type-Options', '').lower() == 'nosniff'


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

\- MISSING: Main application entry file (e.g., app.py) containing error handler configuration

\- MISSING: Configuration file (e.g., config.py) setting the environment mode



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Global error handler middleware and environment configuration



4.3 RootCause

\- The application is likely running in debug mode or lacks a global exception handler, causing it to output detailed error information (stack traces, file paths, line numbers) when an error occurs (404 or malformed input).

\- The default error handling mechanism exposes internal implementation details instead of sanitizing them for production.



4.4 ActionablePlan

\- Target File: app.py

&nbsp; Target: Global error handler registration

&nbsp; Change: Register a generic error handler for common HTTP errors (404, 405, 500) and generic exceptions that returns a sanitized JSON response (e.g., `{"error": "Not found"}` or `{"error": "Internal server error"}`) without stack traces.



\- Target File: config.py (or app configuration)

&nbsp; Target: Environment configuration

&nbsp; Change: Ensure `DEBUG` mode is set to `False` in production environments to suppress the framework's default debugger pages.



4.5 FileToActionMap

\- app.py → Add global error handlers to suppress stack traces.

\- config.py → Disable debug mode.



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