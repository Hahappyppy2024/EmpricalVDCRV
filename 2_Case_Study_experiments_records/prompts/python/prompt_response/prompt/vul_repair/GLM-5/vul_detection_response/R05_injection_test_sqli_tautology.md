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

4.0 VulType

VulType: R03



4.1 RelatedFiles

\- MISSING: Route handler for GET /api/courses/<course\_id>/posts

\- MISSING: Database query logic for searching posts



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Logic handling the 'keyword' query parameter and executing the database search



4.3 RootCause

\- The application builds the SQL search query by concatenating the user-supplied 'keyword' parameter directly into the statement (e.g., `f"SELECT \* FROM posts WHERE content LIKE '%{keyword}%'"`).

\- This allows an attacker to inject SQL commands (like `OR 1=1`) that alter the query logic, resulting in unauthorized data retrieval (expanded result set).



4.4 ActionablePlan

\- Target File: controllers/post\_controller.py (or relevant model file)

&nbsp; Target: Function executing the search query

&nbsp; Change: Refactor the database query to use parameterized queries (prepared statements) or an ORM method that safely binds parameters, ensuring the 'keyword' is treated as data, not executable code.



4.5 FileToActionMap

\- controllers/post\_controller.py → Refactor search query to use parameterized inputs.

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