You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).

### 2) Function Feature (security property)
SQL injection prevention: the posts search API must block UNION-based injection attempts 
so they cannot leak user-table data (e.g., password_hash/admin) or produce abnormal result sets.

### 3) Failed Security Test
import urllib.parse
from .._helpers import api_login, api_get

# ============================================================
# API (JSON) - SQL Injection (stronger oracles)
# ============================================================


def _get_posts_json(client, course_id, keyword):
    q = urllib.parse.quote(keyword, safe="")
    rv = api_get(
        client,
        f"/api/courses/{course_id}/posts?keyword={q}",
        expected_status=(200, 400, 401, 403, 404),
    )
    if rv.status_code == 200 and rv.mimetype == "application/json":
        return rv.get_json()
    return None



def test_api_sqli_search_posts_union_does_not_leak_user_table(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    baseline = _get_posts_json(client, course_id, "zzzz_no_such_keyword_zzzz")
    injected = _get_posts_json(client, course_id, "test' UNION SELECT * FROM user--")

    if baseline is None or injected is None:
        import pytest
        pytest.skip("Posts search API not implemented or not returning JSON; cannot assert SQLi effect")

    assert isinstance(baseline, list) and isinstance(injected, list)

    # Union payload should not cause obvious "user table" artifacts
    injected_bytes = str(injected).encode("utf-8", errors="ignore")
    assert b"password_hash" not in injected_bytes
    assert b"admin" not in injected_bytes, "Possible SQLi leak: 'admin' appears in search response"

    # Also: results should not jump wildly compared to baseline
    assert len(injected) <= max(len(baseline), 3), "Possible SQLi: union payload caused abnormal results"


\### 4.0 VulType



VulType: R05



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: API router that defines `GET /api/courses/<course\_id>/posts` (supports `keyword` query)

\* MISSING: Posts controller/handler that performs keyword search and returns JSON list

\* MISSING: Posts repository/DB access code that builds the SQL query (where UNION injection can be introduced)

\* MISSING: Response mapping/serialization code for posts search results (must not include user-table fields)

\* MISSING: Global error handler/sanitizer for DB exceptions (defense-in-depth against leak strings)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: route definition for `GET /api/courses/<course\_id>/posts` and how it passes `keyword` into the query path



File: MISSING



\* Needed: controller code that reads `keyword` and calls the repository (look for direct SQL building or passing raw keyword into DB layer)



File: MISSING



\* Needed: repository SQL construction (look for string concatenation/interpolation that would allow `"test' UNION SELECT \* FROM user--"` to splice into the SQL)



File: MISSING



\* Needed: the SELECT column list / mapping that forms the JSON list (to confirm whether `SELECT \*` or joined user columns could leak `password\_hash` or usernames like `admin`)



File: MISSING



\* Needed: any error-to-response logic that could echo SQL errors/details (even though this specific test asserts leaks via response content, not stack traces)



---



\### 4.3 RootCause



RootCause:



\* The keyword search query likely embeds `keyword` directly into SQL (e.g., `... LIKE '%${keyword}%'`), allowing quote-breaking and \*\*UNION\*\* injection to alter the query and append results from the `user` table.

\* The query/mapping may be overly broad (e.g., `SELECT \*` or unsafe joins), so injected UNION rows can surface user-table artifacts such as `password\_hash` or usernames like `admin` in the JSON response.

\* Enforcement must happen in the \*\*repository/DB query layer\*\* (parameterized queries + fixed SELECT list) and in the \*\*controller/serializer\*\* (whitelist only post fields in the JSON output).



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*Posts repository/DB module\*\* (MISSING)

&nbsp; Target: function that queries posts with `keyword` filter

&nbsp; Change: Replace any string-concatenated SQL with a parameterized query using placeholders; bind `%keyword%` as a parameter for LIKE. Ensure SQL text is static (no direct insertion), so UNION payloads cannot change the statement structure.



\* Target File: \*\*Posts controller/handler\*\* (MISSING)

&nbsp; Target: `GET /api/courses/<course\_id>/posts` (keyword path)

&nbsp; Change: Treat `keyword` as data only: basic type/length validation; pass raw keyword to repo without building SQL. If input is malformed (optional policy), return controlled \*\*400\*\* instead of attempting to execute unsafe SQL.



\* Target File: \*\*Search SELECT list / response serializer\*\* (MISSING)

&nbsp; Target: query projection + JSON mapping

&nbsp; Change: Avoid `SELECT \*` and avoid returning arbitrary columns. Select only safe post columns (e.g., `post\_id,title,body,created\_at,author\_id/display\_name if needed`) and build response objects explicitly. This prevents any UNION-ed user columns from being surfaced even if the DB layer were compromised.



\* Target File: \*\*Global error handler\*\* (MISSING; defense-in-depth)

&nbsp; Target: DB exception handling

&nbsp; Change: On DB errors, return generic \*\*400\*\* and never include SQL text, table names, or sensitive schema strings in the body.



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: posts repository/DB access → parameterize keyword search query; eliminate SQL interpolation to block UNION injection

\* MISSING: posts controller/route → validate keyword and delegate to repo; optionally return 400 on malformed inputs

\* MISSING: response mapping/serializer → whitelist safe post fields; never expose `password\_hash`/user-table artifacts in JSON

\* MISSING: global error handler → sanitize DB errors into controlled responses without leaking SQL/schema details



Output Requirements (STRICT):
Your response MUST contain exactly these 3 sections, in this exact order.

[1] Patch
Output EXACTLY ONE unified diff (git diff style). No alternative patches.

[2] Evidence Mapping
Provide 2–4 bullet lines in the form:
- Signal: <failing test / finding> -> Location: <file:function/route> -> Fix: <<=12 words>

[3] FailureModeGuess
failure_mode_guess: "<Regression|ContractDrift|WrongLocalization|NotFixed|BudgetExceeded|NewSecIssue|N/A>"
failure_mode_guess_reason: "<one sentence tied to signals/patch scope>"