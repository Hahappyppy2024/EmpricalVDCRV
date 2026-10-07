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

VulnType: R02



\[2] TestMapping

TestMapping:



\* (expected: `POST /api/auth/login` returns 200 \*\*and\*\* the response includes a session `Set-Cookie` line for `PHPSESSID` (or session cookie) containing \*\*HttpOnly\*\*) -> (observed: session cookie line exists but \*\*missing `HttpOnly`\*\*, so assertion fails) -> (code cause: session cookie is being set without `HttpOnly` in the server’s session configuration used by `/api/auth/login`).

\* (expected: same session cookie `Set-Cookie` line includes `SameSite=Lax|Strict|None`) -> (observed: cookie line lacks `SameSite=...` or has an invalid value) -> (code cause: `SameSite` is not configured (or configured to a nonstandard value) for the session cookie).

\* (expected: cookie flags are present immediately on the login response) -> (observed: flags absent on login response) -> (code cause: `/api/auth/login` establishes a session but does not set cookie parameters, so defaults are used and omit required attributes).



\[3] RootCause

RootCause:



\* The session cookie issued during `/api/auth/login` is not configured with `HttpOnly`, allowing client-side script access to the session identifier.

\* The session cookie is not configured with a valid `SameSite` attribute (`Lax/Strict/None`), weakening CSRF protections.

\* The correct enforcement point is the global session/cookie configuration that governs the session cookie (affecting `/api/auth/login` responses), not per-request logic.



\[4] RepairPlan

RepairPlan:



\* Target: session configuration snippet that controls `PHPSESSID` cookie parameters (global session init, before routes) | Change: enable `HttpOnly` for the session cookie so `Set-Cookie: PHPSESSID=...; HttpOnly` is emitted on login.

\* Target: same session configuration snippet | Change: set a valid `SameSite` attribute (`Lax` recommended for typical web apps) so `Set-Cookie` includes `SameSite=Lax` (or `Strict/None` as policy requires).

\* Target: login route `POST /api/auth/login` | Change: ensure login actually triggers session creation on this response (so the cookie with the configured flags is set during login, not deferred).

\* Target: any manual `setcookie()` usage in the login handler (if present) | Change: if session cookies are manually set, add the `httponly` and `samesite` options there as well (avoid mixing manual cookie setting with session defaults).


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