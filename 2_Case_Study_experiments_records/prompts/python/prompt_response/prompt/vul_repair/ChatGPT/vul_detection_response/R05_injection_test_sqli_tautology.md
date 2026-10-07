You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).

### 2) Function Feature (security property)
SQL injection resistance: the posts search API must treat keyword as 
data (not SQL), so a tautology payload (e.g., ' OR 1=1 --) must not broaden 
the result set or expose sensitive database details.

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


def test_api_sqli_search_posts_tautology_does_not_expand_results(client, auth, sample_course):
    auth.login("teacher", "password")
    course_id = sample_course

    # Baseline: a keyword that should return empty or very small results
    baseline = _get_posts_json(client, course_id, "zzzz_no_such_keyword_zzzz")
    injected = _get_posts_json(client, course_id, "' OR 1=1 --")

    # If API not implemented / not returning JSON, we can't judge -> skip
    if baseline is None or injected is None:
        import pytest
        pytest.skip("Posts search API not implemented or not returning JSON; cannot assert SQLi effect")

    # Stronger oracle: injection should NOT increase results compared to baseline
    # (If your app legitimately returns all posts for empty/unknown keyword, adjust this oracle.)
    assert isinstance(baseline, list) and isinstance(injected, list)
    assert len(injected) <= len(baseline), "Possible SQLi: tautology payload expanded result set"

    # Still keep leakage checks
    import urllib.parse

    payload = "' OR 1=1 --"
    q = urllib.parse.quote(payload, safe="")

    rv2 = client.get(f"/api/courses/{course_id}/posts?keyword={q}")
    body = rv2.data or b""
    assert b"password_hash" not in body
    assert b"sqlite_master" not in body
\\### 4.0 VulType







VulType: R05







---







\\### 4.1 RelatedFiles







RelatedFiles:







\\\* MISSING: API router that defines `GET /api/courses/<course\\\_id>/posts` (with optional `keyword` query)



\\\* MISSING: Posts controller/handler that implements listing/searching posts and parses `keyword`



\\\* MISSING: Posts repository/data-access code that builds and executes the SQL query (the SQLi injection point)



\\\* MISSING: Global error/response sanitizer for DB errors (to prevent leakage of `sqlite\\\_master`, schema details, or sensitive columns)







---







\\### 4.2 RelevantCodeInsideFiles







RelevantCodeInsideFiles:



File: MISSING







\\\* Needed: route definition for `GET /api/courses/<course\\\_id>/posts` and how it dispatches to a posts list/search handler (including whether `keyword` triggers a different SQL path)







File: MISSING







\\\* Needed: handler logic that reads `keyword` from query string and chooses the DB query (e.g., `if keyword: ... WHERE ... LIKE ...`)







File: MISSING







\\\* Needed: the exact SQL construction/execution for keyword search (look for string interpolation/concatenation such as `... WHERE body LIKE '%{keyword}%'` or `... AND (title LIKE '%" + keyword + "%')`)







File: MISSING







\\\* Needed: response serialization logic (ensure it doesn’t accidentally include sensitive fields like `password\\\_hash` in JSON and doesn’t dump sqlite error details)







---







\\### 4.3 RootCause







RootCause:







\\\* The posts search query likely embeds `keyword` directly into SQL (string concatenation/interpolation), so a tautology payload (`' OR 1=1 --`) alters the WHERE clause and \\\*\\\*broadens results\\\*\\\* (len(injected) > len(baseline)).



\\\* The query path may also be selecting overly-broad columns (e.g., `SELECT \\\*` across joins), risking leakage of sensitive columns in the response body (the test explicitly checks for `password\\\_hash` and `sqlite\\\_master`).



\\\* The missing/incorrect enforcement belongs in the \\\*\\\*repository/DB query layer\\\*\\\* (parameterized queries) and in the \\\*\\\*controller/serializer\\\*\\\* (whitelist fields returned to JSON).







---







\\### 4.4 ActionablePlan







ActionablePlan:







\\\* Target File: \\\*\\\*Posts API route/controller\\\*\\\* (MISSING)



\&nbsp; Target: `GET /api/courses/<course\\\_id>/posts` (keyword search behavior)



\&nbsp; Change: Treat `keyword` as data: validate it as a string (optional length cap), then pass it to the repository as a parameter (do not build SQL in the controller). Ensure the JSON response includes only post fields (e.g., `post\\\_id`, `title`, `body`, `created\\\_at`, `author\\\_display\\\_name`) and never user auth fields.







\\\* Target File: \\\*\\\*Posts repository / DB access module\\\*\\\* (MISSING)



\&nbsp; Target: SQL query that filters posts by `keyword`



\&nbsp; Change: Replace any interpolated SQL with parameterized SQL placeholders; bind `%keyword%` as a parameter for LIKE clauses. Ensure the WHERE clause cannot be altered by quotes/comment markers. This prevents the tautology from expanding results compared to baseline.







\\\* Target File: \\\*\\\*Posts JSON serialization / query column selection\\\*\\\* (MISSING)



\&nbsp; Target: SELECT list for posts query and response mapping



\&nbsp; Change: Avoid `SELECT \\\*` (especially across joins). Select only required post columns; never include `password\\\_hash` (and avoid joining `users` unless needed for display name). This blocks sensitive-field leakage checks.







\\\* Target File: \\\*\\\*Global DB error handler\\\*\\\* (MISSING; defense-in-depth)



\&nbsp; Target: error-to-response mapping for DB exceptions



\&nbsp; Change: On SQL errors, return controlled \\\*\\\*400\\\*\\\* with a generic error message and no schema strings (avoid echoing `sqlite\\\_master`, SQL text, or stack traces).







---







\\### 4.5 FileToActionMap







FileToActionMap:







\\\* MISSING: posts API route/controller → parse/validate `keyword`; call repo with parameter; ensure JSON whitelist (no `password\\\_hash`)



\\\* MISSING: posts repository/DB module → parameterize keyword LIKE query; remove SQL string interpolation to prevent tautology expansion



\\\* MISSING: posts query/serializer → avoid `SELECT \\\*`; select only safe post fields; prevent sensitive DB field exposure



\\\* MISSING: global error handler → sanitize DB errors into generic 400 without leaking sqlite/schema details





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



