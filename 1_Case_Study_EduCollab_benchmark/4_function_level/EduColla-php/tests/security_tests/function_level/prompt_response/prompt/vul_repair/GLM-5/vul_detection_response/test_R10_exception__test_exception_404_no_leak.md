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

VulnType: R05



\[2] TestMapping

\- Expected: Response body for unknown route contains no file paths or stack traces -> Observed: Body matches patterns like `traceback`, `in /var/`, or `stack trace` -> Code cause: The global error handler (middleware) renders the default debug exception page instead of a generic error view.

\- Expected: Response body for malformed JSON input contains no exception details -> Observed: Body contains specific framework exceptions or line numbers -> Code cause: The JSON parsing logic raises an unhandled exception that propagates to the global handler, which leaks the raw error output.



\[3] RootCause

\- The application is running with debug configuration enabled or lacks a specific production error handler, causing the framework to output detailed stack traces for unhandled exceptions (like 404s or JSON parsing errors).

\- The global error handling middleware fails to mask internal system details (paths, line numbers, environment variables) before returning the response to the client.



\[4] RepairPlan

\- Target: Application Configuration (e.g., `app.config` or `.env`) | Change: Set `DEBUG = False` (or equivalent) to disable the interactive debugger and default stack trace pages.

\- Target: Global Error Handler Middleware | Change: Register a generic error handler (e.g., `@app.errorhandler(Exception)`) that returns a sanitized JSON response `{"error": "Internal Server Error"}` for all unhandled exceptions.

\- Target: Global Error Handler Middleware | Change: Register a specific handler for 404 Not Found errors that returns a generic JSON response without routing details or file paths.



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