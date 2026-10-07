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



\* (expected: `GET /api/courses/:course\_id/uploads/:upload\_id/download` returns 200 and `Content-Type` starts with `application/octet-stream`) -> (observed: `Content-Type` check passes per test comment) -> (code cause: the download handler already forces `Content-Type: application/octet-stream` when serving uploaded files).

\* (expected: same download response includes header `X-Content-Type-Options: nosniff`) -> (observed: header is missing or not equal to `nosniff`, so the assertion fails) -> (code cause: the download route/controller does not set `X-Content-Type-Options` on file responses, and there is no global middleware adding it).



\[3] RootCause

RootCause:



\* The upload download endpoint (`GET /api/courses/:course\_id/uploads/:upload\_id/download`) does not include the hardening header `X-Content-Type-Options: nosniff` on successful file download responses.

\* This must be enforced at the response-building point for downloads (controller) or via a global security-headers middleware that applies to this route.



\[4] RepairPlan

RepairPlan:



\* Target: controller/handler for `GET /api/courses/:course\_id/uploads/:upload\_id/download` | Change: set response header `X-Content-Type-Options` to `nosniff` on the 200 download response (alongside the existing `Content-Type: application/octet-stream`).

\* Target: any shared file-download helper used by the download endpoint (if present) | Change: ensure the helper always attaches `X-Content-Type-Options: nosniff` to the returned response object so all download paths inherit it.

\* Target: global security-headers middleware (if present in the pasted snippets) | Change: add `X-Content-Type-Options: nosniff` for all responses (or at least for `/api/\*` and file download routes), making the defense consistent beyond this one endpoint.


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