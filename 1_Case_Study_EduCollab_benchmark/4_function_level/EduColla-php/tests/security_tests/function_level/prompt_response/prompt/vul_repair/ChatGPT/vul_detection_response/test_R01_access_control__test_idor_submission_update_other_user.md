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

TestMapping:



\* (expected: student `s2` `PUT /api/courses/:course\_id/assignments/:assignment\_id/submissions/:submission\_id` for \*\*someone else’s\*\* submission is rejected with \*\*401/403/404\*\* OR returns 2xx only with explicit denial/no-op semantics) -> (observed: request returns \*\*2xx without denial markers\*\*, or returns an unacceptable status like 400) -> (code cause: the update route lacks an ownership/membership authorization check for `submission\_id` against `req.session.user`, or it treats any course member as allowed to edit any submission).

\* (expected: regardless of status, submission content must not change when attacker is not the owner) -> (observed: `after\_content != before\_content` (e.g., becomes `"hacked"`)) -> (code cause: update handler performs the DB update before verifying ownership, or verifies using the wrong field (e.g., compares against assignment creator / course role rather than submission author), allowing IDOR side effects).



\[3] RootCause

RootCause:



\* The submission update endpoint (`PUT /api/courses/:course\_id/assignments/:assignment\_id/submissions/:submission\_id`) does not enforce \*\*submission ownership\*\*: it fails to confirm that the authenticated user is the submission’s author (or otherwise privileged) before permitting updates.

\* The authorization boundary is misplaced or incomplete: the handler reaches the “update submission” DB call without first verifying `(submission\_id belongs to current\_user)` within the same course/assignment scope.

\* Even if the endpoint returns an error sometimes, the absence of a strict pre-update check allows unauthorized side effects (content changes) that the test detects via admin re-read.



\[4] RepairPlan

RepairPlan:



\* Target: route/controller for `PUT /api/courses/:course\_id/assignments/:assignment\_id/submissions/:submission\_id` | Change: before any update, load the submission by `submission\_id` (and confirm it matches the given `course\_id`/`assignment\_id`), then enforce: allow only (a) submission owner, or (b) teacher/assistant/admin if your policy permits; otherwise return \*\*403\*\*.

\* Target: authorization/middleware used for course-scoped endpoints | Change: ensure the update route is behind “require login” and “require course member” checks; still keep per-resource ownership check in the controller.

\* Target: repository/service function that updates submission content | Change: add a defensive condition so the update occurs only when `submission.user\_id == current\_user\_id` (or privileged role); if unauthorized, perform \*\*no DB write\*\* and return a no-op indicator.

\* Target: response behavior of the update route | Change: for unauthorized attempts, return \*\*403\*\* (preferred) with a clear error string (e.g., contains “forbidden”) and do not return 2xx unless you intentionally implement a no-op with explicit denial semantics (but prefer 403 to satisfy the test’s strictness and clarity).



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