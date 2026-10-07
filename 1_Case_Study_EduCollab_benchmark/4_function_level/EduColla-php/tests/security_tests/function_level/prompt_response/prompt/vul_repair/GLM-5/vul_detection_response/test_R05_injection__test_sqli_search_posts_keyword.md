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

\- Expected: Injection payload `' OR 1=1 --` does not return unrelated posts (e.g., `t2`) -> Observed: `t2` is present in `inj\_titles` -> Code cause: The `GET /api/courses/{course\_id}/search/posts` endpoint constructs the SQL query using string concatenation/interpolation with the `keyword` parameter.

\- Expected: `len(inj\_posts) <= len(none\_posts)` (result set should not broaden) -> Observed: `len(inj\_posts) > len(none\_posts)` -> Code cause: The injected tautology `OR 1=1` modifies the query logic to return all rows, bypassing the `WHERE` clause filter.

\- Expected: Database treats input as data -> Observed: Database treats input as executable code -> Code cause: The repository/database execution layer fails to use parameterized queries (prepared statements) for the search functionality.



\[3] RootCause

\- The search logic in the controller or repository layer directly interpolates the user-supplied `keyword` into the SQL query string (e.g., `f"SELECT ... WHERE title LIKE '%{keyword}%'"`).

\- The application fails to sanitize or parameterize the input, allowing the single quote in the payload to break out of the data context and alter the query structure.



\[4] RepairPlan

\- Target: Route `/api/courses/<course\_id>/search/posts` (Controller/Repository) | Change: Locate the SQL query construction for the post search.

\- Target: Route `/api/courses/<course\_id>/search/posts` (Controller/Repository) | Change: Replace the string interpolation of the `keyword` variable with a parameter placeholder (e.g., `?` or `%s`).

\- Target: Database Execution Call (Controller/Repository) | Change: Pass the `keyword` variable (with wildcards added programmatically if needed, e.g., `f"%{keyword}%"`) as a separate argument to the execute function (e.g., `cursor.execute(sql, (param\_val,))`).



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