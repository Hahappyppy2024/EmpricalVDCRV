\[1] VulnType

VulnType: R05



\[2] TestMapping

TestMapping:



\* (expected: `GET /api/courses/:course\_id/posts?keyword=test' UNION SELECT \* FROM user--` returns a JSON list \*\*without\*\* user-table artifacts like `password\_hash` or `admin`) -> (observed: `injected` JSON contains `admin` and/or `password\_hash` substrings) -> (code cause: the search query in `GET /api/courses/:course\_id/posts` is constructed unsafely from `keyword`, allowing UNION injection to alter selected rows/columns and leak user-table data).

\* (expected: UNION payload must not produce abnormal result set size compared to baseline) -> (observed: `len(injected)` is much larger than `max(len(baseline), 3)`) -> (code cause: injection changes the SQL semantics (e.g., forces additional rows via UNION), indicating missing parameter binding and possibly overly broad `SELECT \*` serialization).



\[3] RootCause

RootCause:



\* The posts search endpoint (`GET /api/courses/:course\_id/posts` with `keyword=...`) does not treat `keyword` as data; it is incorporated into SQL in a way that enables UNION-based injection.

\* The endpoint likely selects/serializes excessive columns (e.g., `SELECT \*` or joining users) so that if injection succeeds, sensitive user fields (including `password\_hash`) and recognizable values (`admin`) can appear in the JSON response.



\[4] RepairPlan

RepairPlan:



\* Target: route/controller for `GET /api/courses/:course\_id/posts` (keyword search path) | Change: replace any string-built SQL with a parameterized query using placeholders for the keyword; never concatenate `keyword` into SQL (this blocks UNION payloads from changing the query structure).

\* Target: DB query projection used by the posts list/search endpoint | Change: explicitly select only post columns needed for the response (no `SELECT \*`, no user credential columns); ensure the JSON serializer cannot emit `password\_hash` even if a query is manipulated.

\* Target: optional input validation for `keyword` | Change: enforce a reasonable length limit and reject obviously malicious patterns only as a secondary guard (primary defense remains parameter binding); return \*\*400\*\* for invalid inputs.

\* Target: error handling around the search query | Change: if the DB rejects the query (syntax errors), return a controlled \*\*400\*\* with a generic message and do not echo SQL fragments or table names.



\[5] ReliabilityClaim

ReliabilityClaim: The test calls the posts search endpoint with a UNION payload and fails only if the JSON response contains `password\_hash`/`admin` artifacts or an abnormally large list, which directly indicates unsafe SQL construction and/or over-broad field selection in that endpoint.



\[6] RegressionRisks

RegressionRisks:



\* Changing from `SELECT \*` to explicit columns may affect clients that implicitly relied on extra fields in the posts JSON; recheck API response shape compatibility.

\* Adding stricter keyword validation may reject edge-case search strings; recheck normal search functionality and any tests using punctuation-heavy keywords.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan blocks UNION injection via parameterization and prevents sensitive fields from ever being returned by narrowing the selected/serialized columns."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference the exact tested endpoint `/api/courses/:course\_id/posts?keyword=...` and the exact oracles in the test (`password\_hash`, `admin`, and abnormal length compared to baseline)."



