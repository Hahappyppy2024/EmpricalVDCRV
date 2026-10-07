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

4.0 VulType

VulType: R05



4.1 RelatedFiles

\- MISSING: Main application entry file (e.g., app.py) containing error handlers

\- MISSING: Request parsing logic or middleware (e.g., JSON body parser)



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Global error handler configuration and JSON parsing logic



4.3 RootCause

\- The application does not catch JSON parsing exceptions (e.g., `json.JSONDecodeError` or `BadRequest`) that occur when processing malformed request bodies.

\- In a development or misconfigured production environment, the framework's default error response includes a full stack trace and error details, leaking sensitive implementation information.



4.4 ActionablePlan

\- Target File: app.py (or extensions/initialization file)

&nbsp; Target: Global error handler registration

&nbsp; Change: Register a global error handler for JSON parsing exceptions (e.g., `werkzeug.exceptions.BadRequest` or `json.JSONDecodeError`) that returns a generic 400 Bad Request JSON response without stack traces.



\- Target File: config.py (or app configuration)

&nbsp; Target: Application configuration settings

&nbsp; Change: Ensure debug mode is disabled in production (`DEBUG = False`) to suppress default stack trace output.



4.5 FileToActionMap

\- app.py → Add specific error handler for malformed JSON exceptions.

\- config.py → Verify production configuration suppresses debug output.

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