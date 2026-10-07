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



### 4.0 VulType

VulType: R10

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: global error handler / exception handler for API routes (controls 4xx/5xx body)
* MISSING: router/app bootstrap where 404/405 are produced for unknown `/api/*` routes
* MISSING: login route/controller for `POST /api/auth/login` (JSON parsing + error handling path)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the code that handles unknown routes under `/api/*` (404 handler) and what body it returns (must confirm it does not include stack traces / file paths).

File: MISSING

* Needed: the JSON parsing layer for `POST /api/auth/login` (where malformed JSON triggers an exception) and the handler that formats the error response.

File: MISSING

* Needed: any global “debug” / “development mode” settings that cause detailed exception pages (stack traces, file paths) to be rendered.

---

### 4.3 RootCause

RootCause:

* Malformed JSON sent to `POST /api/auth/login` is likely causing an unhandled JSON parse exception (or framework default error page), resulting in a **500** with leaked details (stack traces / file paths / exception text) that match the test’s `LEAK_PATTERNS`.
* Unknown `/api/*` routes may be returning a default framework 404/405 body (or a debug page) that includes internal details such as “Cannot …” pages, stack traces, or path info (especially if debug is enabled).
* Missing or incorrect **global error handling**: no centralized handler that converts exceptions (JSON decode errors, routing errors) into a controlled JSON/plain response without internal details.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: MISSING (app/bootstrap where middleware and error handlers are registered)
  Target: global API error-handling layer (before/after routes)
  Change: Add a centralized error handler for `/api/*` that:

    * catches JSON parse errors / bad request exceptions and returns **400** (or **415/422**) with a minimal body (e.g., `{"error":"bad_request"}`) **without** exception messages/stack/path.
    * catches generic exceptions and returns **500** with a generic body (e.g., `{"error":"internal_error"}`) **without** any internal details.
    * ensures Content-Type is consistent (JSON for API).

* Target File: MISSING (route/controller for `POST /api/auth/login`)
  Target: login handler JSON parsing
  Change: Ensure the handler safely reads JSON:

    * If request body is not valid JSON (decode error), return **400** immediately (no throw).
    * Do not echo back parsing exceptions in the response.

* Target File: MISSING (routing for unknown `/api/*` endpoints)
  Target: 404/405 handler for `/api/*`
  Change: Override default 404/405 bodies for API paths to a minimal response (JSON or empty) and ensure debug pages are never served for `/api/*`.

* Target File: MISSING (config)
  Target: debug/error-display configuration
  Change: Disable debug traceback rendering in the test/runtime environment (ensure production-style error pages), so stack traces/file paths never appear in responses.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: app/bootstrap error handler registration → add centralized `/api/*` exception + 404/405 handlers returning sanitized bodies and controlled 4xx/5xx codes
* MISSING: `POST /api/auth/login` controller → catch malformed JSON and return 400 without leaking exception details
* MISSING: API routing/404 handler → ensure unknown API routes return minimal 404/405 without framework debug content
* MISSING: runtime/config → disable debug tracebacks / detailed error pages in responses


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