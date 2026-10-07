
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

\- Expected: `Set-Cookie` header contains `HttpOnly` attribute -> Observed: `HttpOnly` attribute missing from session cookie string -> Code cause: The session configuration (likely in `app.config` or `session\_settings`) lacks the `SESSION\_COOKIE\_HTTPONLY` setting or sets it to `False`.

\- Expected: `Set-Cookie` header contains `SameSite` attribute -> Observed: `SameSite` attribute missing or invalid -> Code cause: The session configuration lacks the `SESSION\_COOKIE\_SAMESITE` setting (should be 'Lax' or 'Strict').



\[3] RootCause

\- The application's session management middleware is initialized with default security settings, which often exclude `HttpOnly` and `SameSite` protections in older frameworks or default configurations.

\- Specifically, the configuration for the session cookie (e.g., Flask's `SESSION\_COOKIE\_HTTPONLY` and `SESSION\_COOKIE\_SAMESITE`) is not explicitly enforced in the application setup.



\[4] RepairPlan

\- Target: Application Configuration (e.g., `app.config` dictionary or config file) | Change: Set `SESSION\_COOKIE\_HTTPONLY` to `True` to prevent client-side script access to the session ID.

\- Target: Application Configuration (e.g., `app.config` dictionary or config file) | Change: Set `SESSION\_COOKIE\_SAMESITE` to `'Lax'` or `'Strict'` to mitigate Cross-Site Request Forgery (CSRF) risks.

\- Target: Session Middleware Initialization | Change: Verify that the session interface applies these settings when setting the `Set-Cookie` header during login.


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