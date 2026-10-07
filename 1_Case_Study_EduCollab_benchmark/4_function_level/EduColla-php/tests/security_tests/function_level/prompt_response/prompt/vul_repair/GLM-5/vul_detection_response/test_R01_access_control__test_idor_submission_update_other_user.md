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

VulnType: R01



\[2] TestMapping

\- Expected: PUT request to `/api/courses/{course\_id}/assignments/{assignment\_id}/submissions/{submission\_id}` by Student 2 returns 401/403/404 -> Observed: Request likely returns 200/204 or fails with 400 (validation error) while processing the update -> Code cause: The route handler processes the update based on valid course/assignment membership without verifying that the current user is the owner of the specific `submission\_id`.

\- Expected: Submission content remains unchanged ("from s1") -> Observed: Submission content changes to "hacked" -> Code cause: The update logic in the controller executes the database update statement (e.g., `UPDATE submissions SET content=... WHERE id=...`) without an ownership check in the WHERE clause or preceding logic.



\[3] RootCause

\- The submission update endpoint (`/api/.../submissions/{submission\_id}`) lacks an authorization check to verify that the `current\_user.id` matches the `user\_id` associated with the `submission\_id` being modified.

\- The application relies on course membership for authentication but fails to enforce object-level ownership (IDOR), allowing any member of the course to manipulate any submission within that course context.



\[4] RepairPlan

\- Target: Submission Update Route Handler (PUT `/api/courses/<course\_id>/assignments/<assignment\_id>/submissions/<submission\_id>`) | Change: Fetch the submission object using `submission\_id` and verify that `submission.user\_id` equals `current\_user.id` before proceeding.

\- Target: Submission Update Route Handler | Change: If `submission.user\_id` does not match `current\_user.id`, abort the request immediately with HTTP 403 Forbidden.

\- Target: Submission Update Logic/Repository | Change: Ensure the database update query explicitly checks for ownership (e.g., `UPDATE ... WHERE id = ? AND user\_id = ?`) to prevent accidental overwrites even if the controller check is bypassed.


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