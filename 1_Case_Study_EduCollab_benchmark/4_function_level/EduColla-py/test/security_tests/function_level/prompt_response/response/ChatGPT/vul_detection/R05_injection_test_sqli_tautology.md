\[1] VulnType

VulnType: R05



\[2] TestMapping

TestMapping:



\* (expected: `GET /api/courses/:course\_id/posts?keyword=' OR 1=1 --` returns a JSON list whose length is \*\*<=\*\* the baseline keyword `zzzz\_no\_such\_keyword\_zzzz`) -> (observed: `len(injected) > len(baseline)` so the assertion fails) -> (code cause: the posts listing/search handler for `GET /api/courses/:course\_id/posts` interpolates `keyword` into the SQL `WHERE` clause (or equivalent) rather than using parameter binding, allowing a tautology to broaden results).

\* (expected: response body must not expose sensitive schema/fields like `password\_hash` or `sqlite\_master`) -> (observed: response bytes contain `password\_hash` and/or `sqlite\_master`) -> (code cause: the handler selects overly broad columns / joins users table and serializes raw rows, or it leaks SQL/debug output when errors occur during search).



\[3] RootCause

RootCause:



\* The `keyword` filter in `GET /api/courses/:course\_id/posts` is implemented with unsafe SQL construction (string concatenation / f-string / template), so `"' OR 1=1 --"` changes query semantics and expands the result set.

\* The handler may be returning more data than necessary (e.g., serializing joined user records including `password\_hash`) or leaking internal schema details (`sqlite\_master`) via debug/error responses.



\[4] RepairPlan

RepairPlan:



\* Target: route/controller for `GET /api/courses/:course\_id/posts` (keyword search branch) | Change: rewrite the DB query to use \*\*parameterized placeholders\*\* for `keyword` (e.g., `WHERE title LIKE ? OR body LIKE ?`) and pass the bound value (e.g., `%keyword%`) as parameters; do not concatenate `keyword` into SQL.

\* Target: same handler’s handling of special characters in `keyword` | Change: treat `keyword` as data; optionally escape `%`/`\_` for LIKE semantics (so attacker can’t force full-table matches via wildcards) while keeping parameterization.

\* Target: response serialization for posts list | Change: return only post fields required by the API; explicitly exclude any user credential fields (ensure `password\_hash` is never selected/serialized).

\* Target: error handling around the search query | Change: on query errors, return a controlled 400 (or 200 with empty list) without embedding SQL text, table names, or stack traces in the response body.



\[5] ReliabilityClaim

ReliabilityClaim: The test compares baseline vs injected keyword result lengths on `GET /api/courses/:course\_id/posts` and additionally scans the raw response bytes for `password\_hash`/`sqlite\_master`, so a failure directly indicates unsafe query construction and/or over-broad/leaky response content in that endpoint.



\[6] RegressionRisks

RegressionRisks:



\* Changing to parameterized LIKE queries may alter search matching behavior (case-sensitivity, wildcard handling); recheck normal keyword search results.

\* If some clients relied on returning all posts for unknown keywords, tightening semantics (or escaping wildcards) may change outputs; recheck product expectations for search behavior.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan prevents tautology payloads from changing SQL semantics (parameter binding) and prevents sensitive fields/schema strings from appearing in the response."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference the exact tested endpoint `/api/courses/:course\_id/posts?keyword=...` and the two explicit oracles: result-set expansion and `password\_hash`/`sqlite\_master` leakage checks."



