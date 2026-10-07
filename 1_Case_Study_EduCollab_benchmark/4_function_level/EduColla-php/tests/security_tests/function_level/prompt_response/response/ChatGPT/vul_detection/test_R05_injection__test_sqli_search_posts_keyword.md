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



\[5] ReliabilityClaim

ReliabilityClaim: The test demonstrates that the injection keyword causes unrelated posts and a larger result set than a nonsense keyword on the same `/api/courses/:course\_id/search/posts` endpoint, which directly indicates the keyword is being interpreted as SQL rather than bound as data.



\[6] RegressionRisks

RegressionRisks:



\* Search behavior may change for inputs containing `%`, `\_`, or quotes; recheck expected matching semantics after escaping/parameterization.

\* If the current implementation uses multiple search fields (title/body), ensure parameterization is applied consistently across all fields to avoid partial fixes.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan directly addresses the broadening symptoms the test asserts by parameterizing the search query at the `/api/courses/:course\_id/search/posts` DB boundary."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference only the concrete endpoint and assertions in the test: baseline finds t1, injection must not return t2, and injection result size must not exceed nonsense query."



