You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


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

\### 4.0 VulType



VulType: R10



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: Flask app factory / main entry where JSON parsing and error handlers are configured (e.g., `app.py`, `\_\_init\_\_.py`)

\* MISSING: API route/controller for `POST /api/courses` (course creation endpoint)

\* MISSING: Global exception/error handler for API errors (Flask `@app.errorhandler(...)` / blueprint error handlers)

\* MISSING: Auth/session middleware used by `auth.login(...)` (not the failure focus but affects request path)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: the `POST /api/courses` route handler showing how it reads JSON (e.g., `request.get\_json()` / `request.json`) and what happens on malformed JSON



File: MISSING



\* Needed: any global error handlers (e.g., handlers for `BadRequest`, `HTTPException`, generic `Exception`) and whether they return debug details (`repr(e)`, `e.description`, `traceback`, etc.)



File: MISSING



\* Needed: app config toggles that might enable Werkzeug debugger or verbose error pages (e.g., `DEBUG=True`, `PROPAGATE\_EXCEPTIONS`, `TRAP\_HTTP\_EXCEPTIONS`, `TESTING`)



---



\### 4.3 RootCause



RootCause:



\* Malformed JSON (`{"title":`) triggers a parsing exception (commonly Flask/Werkzeug `BadRequest` / `JSONDecodeError`). The app likely returns a \*\*500\*\* or an error body that includes \*\*Werkzeug/traceback/file paths/sqlite3 errors/SECRET\_KEY text\*\*, failing the “no leak” regex checks.

\* The JSON parsing path likely uses `request.get\_json(force=True)` or otherwise raises, and there is no hardened error handler to convert this into a controlled \*\*400/415/422\*\* with a sanitized body.

\* Enforcement must occur at the \*\*global API error-handling boundary\*\* (and optionally at the `POST /api/courses` controller) so parse errors never propagate to default debug pages or raw exception dumps.



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*App factory / bootstrap\*\* (MISSING)

&nbsp; Target: Flask app initialization

&nbsp; Change: Disable debug-style exception propagation in the test/runtime configuration (ensure `DEBUG` is off for API responses). Register hardened error handlers for JSON parse failures and generic exceptions.



\* Target File: \*\*Global error handler module\*\* (MISSING)

&nbsp; Target: `@app.errorhandler(400)` / `@app.errorhandler(BadRequest)` (and/or `@app.errorhandler(HTTPException)`)

&nbsp; Change: For malformed JSON / bad request, return \*\*400\*\* (or 415/422) with a minimal JSON body like `{"error":"invalid\_json"}` and \*\*never\*\* include traceback, Werkzeug debugger strings, file paths, sqlite exceptions, or config secrets.



\* Target File: \*\*Courses API controller\*\* (MISSING)

&nbsp; Target: `POST /api/courses`

&nbsp; Change: Parse JSON using a safe pattern: if parsing fails, immediately return \*\*400/422\*\* with a generic message; do not log/echo exception text into the response. Ensure the handler doesn’t bubble raw exceptions upward.



\* Target File: \*\*Logging / error formatting\*\* (if present) (MISSING)

&nbsp; Target: any code that builds response bodies from exceptions

&nbsp; Change: Remove/avoid returning `str(e)`, `repr(e)`, `traceback.format\_exc()`, or config values. Keep detailed errors only in server logs (not response body).



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: app factory/bootstrap → ensure debug error pages are not exposed; register hardened error handlers

\* MISSING: global error handlers → catch malformed JSON/BadRequest and return sanitized 400/415/422 JSON without leak strings

\* MISSING: `POST /api/courses` controller → safely handle `request.get\_json()` failures and return controlled 4xx

\* MISSING: error/exception response formatter → ensure no stack traces, file paths, sqlite errors, or secrets are included in API responses




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

