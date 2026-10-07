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

\- Expected: Response headers contain `X-Content-Type-Options: nosniff` -> Observed: Header is missing -> Code cause: The download route handler (`/api/courses/.../uploads/{id}/download`) fails to set this header in the response object.

\- Expected: Response `Content-Type` is `application/octet-stream` -> Observed: (Implied pass/failure based on snippet context) -> Code cause: The route likely relies on default framework behavior or file extension mapping instead of explicitly forcing the safe MIME type.



\[3] RootCause

\- The controller logic for the download endpoint returns the file content without explicitly adding security hardening headers.

\- Specifically, it omits `X-Content-Type-Options: nosniff` and does not explicitly override the Content-Type to `application/octet-stream`, leaving the client vulnerable to MIME type confusion attacks.



\[4] RepairPlan

\- Target: Route `/api/courses/<course\_id>/uploads/<upload\_id>/download` (Controller) | Change: Add `response.headers\['X-Content-Type-Options'] = 'nosniff'` to the response object before returning.

\- Target: Route `/api/courses/<course\_id>/uploads/<upload\_id>/download` (Controller) | Change: Explicitly set `response.headers\['Content-Type'] = 'application/octet-stream'` to ensure the browser treats the file as a download rather than executable content.

\- Target: Route `/api/courses/<course\_id>/uploads/<upload\_id>/download` (Controller) | Change: Add `Content-Disposition: attachment` header to further enforce download behavior.


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