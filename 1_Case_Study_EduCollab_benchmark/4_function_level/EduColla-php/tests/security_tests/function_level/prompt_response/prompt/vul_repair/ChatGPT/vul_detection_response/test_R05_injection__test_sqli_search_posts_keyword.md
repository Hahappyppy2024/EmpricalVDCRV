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

TestMapping:



\* (expected: baseline `GET /api/courses/:course\_id/search/posts?keyword=SAFE\_UNIQUE\_TITLE\_12345` returns JSON containing the matching post title `t1`) -> (observed: baseline passes (test reaches injection checks), so endpoint exists and search returns JSON) -> (code cause: `GET /api/courses/:course\_id/search/posts` is implemented and performs a keyword filter).

\* (expected: injection `keyword="' OR 1=1 --"` must \*\*not\*\* return unrelated post `t2`) -> (observed: `t2` appears in `inj\_titles`, failing `assert t2 not in inj\_titles`) -> (code cause: search query construction in `GET /api/courses/:course\_id/search/posts` is vulnerable to SQLi tautology, broadening the WHERE clause and returning additional posts).

\* (expected: `len(inj\_posts) <= len(none\_posts)` for nonsense keyword) -> (observed: `len(inj\_posts) > len(none\_posts)`) -> (code cause: keyword is being interpolated into SQL (string concatenation / formatting) rather than passed as a bound parameter, so injection changes query semantics vs a normal “no match” query).



\[3] RootCause

RootCause:



\* The post-search API (`GET /api/courses/:course\_id/search/posts`) does not treat `keyword` as data; it likely concatenates `keyword` into an SQL statement (e.g., `... LIKE '%${keyword}%'`), allowing the payload `"' OR 1=1 --"` to alter the WHERE clause and broaden results.

\* The vulnerability must be fixed at the DB query boundary in the search controller/repo: parameter binding (and proper LIKE escaping if used) is missing or incorrect.



\[4] RepairPlan

RepairPlan:



\* Target: controller/handler for `GET /api/courses/:course\_id/search/posts` | Change: replace any string-built SQL that embeds `keyword` with a parameterized query using placeholders; pass the keyword pattern as a bound value (e.g., `%keyword%`) rather than concatenating it.

\* Target: DB/repo function used by the search endpoint (the code that executes the SELECT for posts) | Change: ensure the `course\_id` and `keyword` are both bound parameters; do not build SQL fragments from raw `keyword`.

\* Target: keyword-to-LIKE pattern construction in the search endpoint | Change: optionally escape `%` and `\_` in user input before adding wildcards to prevent wildcard-based broadening while still using bound parameters.

\* Target: response assembly for search results | Change: keep returning the same JSON shape, but ensure the query logic cannot be widened by injected operators/comments.



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