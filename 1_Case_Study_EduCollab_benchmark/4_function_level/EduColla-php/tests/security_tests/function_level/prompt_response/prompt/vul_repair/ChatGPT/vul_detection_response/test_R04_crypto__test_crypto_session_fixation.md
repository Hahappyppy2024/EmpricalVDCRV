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



\* (expected: a pre-login request to `/login` or `/api/auth/me` sets a session cookie `PHPSESSID` so `sid\_before` is captured) -> (observed: `sid\_before` is captured (test did not skip) and remains present) -> (code cause: the app initializes a PHP session for anonymous visitors on these routes).

\* (expected: after successful `POST /api/auth/login`, the session id in cookie jar (`sid\_after`) must differ from `sid\_before`) -> (observed: `sid\_after == sid\_before`, causing the primary assertion to fail) -> (code cause: login handler authenticates user but does \*\*not\*\* regenerate/rotate the PHP session id (missing `session\_regenerate\_id(true)` after successful login)).

\* (expected: if `Set-Cookie` is present on login, it should carry the new `PHPSESSID`) -> (observed: either no `Set-Cookie` for `PHPSESSID` on login, or it sets the same value) -> (code cause: session id is unchanged, so no new cookie is issued (or it reissues identical id), consistent with lack of regeneration).



\[3] RootCause

RootCause:



\* The login endpoint (`POST /api/auth/login`) establishes authentication within the existing anonymous session but does not rotate the session identifier, leaving the pre-login `PHPSESSID` reusable (session fixation risk).

\* The correct enforcement point is immediately after credential verification succeeds and before sending the 200 response: regenerate the session id and bind the authenticated user to the new session.



\[4] RepairPlan

RepairPlan:



\* Target: controller/handler for `POST /api/auth/login` | Change: after verifying username/password and \*\*before\*\* writing any response body, call PHP session id regeneration with deletion of the old session (e.g., regenerate/rotate the id and keep session data), then set authenticated user info in the regenerated session.

\* Target: same login handler’s session initialization logic | Change: ensure `session\_start()` has occurred before attempting regeneration, and ensure no output is sent before regeneration (so headers/cookie can be updated).

\* Target: session cookie issuance path on login | Change: ensure the regenerated session id results in an updated `Set-Cookie: PHPSESSID=...` on the login response (or at least the next response), so the client’s cookie jar reflects the new id.

\* Target: `/api/auth/me` handler (used by the test to confirm login) | Change: no behavior change required beyond ensuring it reads the regenerated session and returns 200 with JSON containing `"user"`.



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