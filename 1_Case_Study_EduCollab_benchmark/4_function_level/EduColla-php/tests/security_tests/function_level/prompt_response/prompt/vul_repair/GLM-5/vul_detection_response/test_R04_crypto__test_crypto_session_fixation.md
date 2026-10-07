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

VulnType: R07



\[2] TestMapping

\- Expected: `sid\_after != sid\_before` (session ID changes upon login) -> Observed: `sid\_after == sid\_before` (Test assertion failure) -> Code cause: The `/api/auth/login` route establishes the authenticated state without invalidating the existing session identifier.

\- Expected: Login response includes `Set-Cookie` header with a new session ID -> Observed: No `Set-Cookie` header sent or value matches the request cookie -> Code cause: The login controller fails to call the session regeneration function (e.g., `session\_regenerate\_id(True)` or framework equivalent).



\[3] RootCause

\- The authentication logic in the `/api/auth/login` endpoint updates the server-side session data to mark the user as "logged in" but reuses the session ID provided in the request.

\- The missing step is the explicit destruction of the old session ID and the creation of a new one, which allows an attacker to fixate a known session ID prior to victim login.



\[4] RepairPlan

\- Target: Route `/api/auth/login` (Controller) | Change: Locate the code block immediately following successful credential verification.

\- Target: Route `/api/auth/login` (Controller) | Change: Insert a call to regenerate the session ID (e.g., `session.regenerate()` or `session\_regenerate\_id(true)`) before setting the authenticated user ID in the session.

\- Target: Route `/api/auth/login` (Controller) | Change: Ensure the new session ID is sent back to the client via a `Set-Cookie` header in the response.



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