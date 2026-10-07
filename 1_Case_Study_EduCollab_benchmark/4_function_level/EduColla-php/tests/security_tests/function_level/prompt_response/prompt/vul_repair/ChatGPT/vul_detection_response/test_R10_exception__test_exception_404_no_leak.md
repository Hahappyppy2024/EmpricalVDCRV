You are a web application security engineer.

Task: Apply the repair plan to fix the failing security test with a minimal patch.
Output exactly ONE patch (no alternatives). If this is an iterative run, treat this as one round.

### 1) Target security test (must be fixed)
- Test ID: {}


- Observed failure (paste key error/status/assert):
{

}


- Related code area (optional):
{

}






### 2) From the vul detection step (copy-paste)     

\[1] VulnType

VulnType: R06



\[2] TestMapping

TestMapping:



\* (expected: `GET /api/\_\_definitely\_not\_a\_real\_route` returns 404/400/405 \*\*without\*\* leaking stack traces/paths) -> (observed: response body matches a leak regex such as `stack trace`, `traceback`, `at ...(:\\d+)`, `in /var/`, or PHP notice/warning patterns) -> (code cause: the app’s default 404/route-miss handling (or debug/error page) returns verbose framework/runtime output instead of a sanitized error body).

\* (expected: `POST /api/auth/login` with `Content-Type: application/json` and body `{not-json` returns an allowed status (400/401/403/415/422/500) \*\*without\*\* leaking internals) -> (observed: response body contains leaked exception details like “uncaught exception”, “traceback”, file paths, or line numbers) -> (code cause: JSON parse/validation errors propagate to the client via default exception handler or error middleware that renders raw exception text / stack details).



\[3] RootCause

RootCause:



\* API error paths are not sanitized: unknown routes and malformed JSON requests are handled by default framework/runtime error pages or unguarded exception propagation that includes internal details (stack traces, file paths, line numbers, DB exception names).

\* The correct enforcement point is the global error/404 handler (API-wide) and JSON parsing error handling for `/api/auth/login`, ensuring all error responses are controlled and non-verbose.



\[4] RepairPlan

RepairPlan:



\* Target: global 404 handler for `/api/\*` routes (route-miss handling) | Change: return a minimal JSON error body (or minimal text) for unknown API routes with status 404, avoiding any framework/debug “Cannot GET” pages that may include stack/path details.

\* Target: global exception/error middleware (app-wide) | Change: ensure exceptions are caught and responses are sanitized (no stack traces, no file paths, no raw exception messages); return generic messages for 5xx and generic validation messages for 4xx.

\* Target: JSON request parsing for `POST /api/auth/login` | Change: add a malformed-JSON guard so JSON decode failures return \*\*400/415/422\*\* with a generic “Invalid JSON” style message and no exception details.

\* Target: configuration affecting error verbosity (debug/dev settings) | Change: disable debug/error detail rendering for API responses so production-style error bodies are used in this test environment.


### 3) Editable code (ONLY what is pasted exists)
IMPORTANT: Only modify the code shown here. Do not add new dependencies.
{PASTE CODE SNIPPETS / FILE CONTENTS TO EDIT}

### 4) Failure-mode definitions (choose exactly ONE if the target is not fixed)
- Regression: introduces new functional test failures (new functional FAIL after patch).
- ContractDrift: changes API/behavioral contract or disables/removes intended functionality to force a pass.
- WrongLocalization: edits do not touch the failing execution path; target security test still fails.
- NotFixed: target security test still fails after patch; no more specific mode applies.
- BudgetExceeded: iteration budget reached without fixing the target.
- NewSecIssue: introduces new security failures that previously passed (if unobservable, report N/A).

### 5) Output Requirements (STRICT)
Your response MUST contain exactly the following 5 sections, in this exact order.
Do NOT add extra sections. Do NOT include explanations outside the required sections.

[1] Patch
Output ONLY a unified diff (git diff style).
Patch:
{UNIFIED DIFF ONLY}

[2] Verification
Provide how to verify both security and functionality.
Verification:
- TargetSecurityTest: {command} | Expected: PASS
- FunctionalTests: {command} | Expected: no new failures
- SecuritySuite (optional): {command} | Expected: no worse than before

[3] OutcomeRecording (fill AFTER running tests; do NOT guess)
OutcomeRecording:
- FixSuccess (target test pass?): {PASS/FAIL}
- FuncReg (new functional failures count): {0 or count}
- NewSec (new security failures count): {N/A if unobservable, else count}
- RemainingSecFailures (count): {count}

[4] FailureModeIfNotFixed
FailureModeIfNotFixed: {Regression | ContractDrift | NewSecIssue | WrongLocalization | NotFixed | BudgetExceeded | N/A}
Rationale: {one sentence}

[5] SelfCheck
SelfCheck:
- PatchProduced: YES/NO | Evidence: diff markers present (diff --git or ---/+++ with @@)
- Minimality: YES/NO | Evidence: number of files/lines changed (approx)

Constraints:
- Do NOT invent filenames, endpoints, tests, or code not shown.
- Keep the patch minimal and localized.
- Preserve intended behavior; do not bypass functionality to satisfy tests.