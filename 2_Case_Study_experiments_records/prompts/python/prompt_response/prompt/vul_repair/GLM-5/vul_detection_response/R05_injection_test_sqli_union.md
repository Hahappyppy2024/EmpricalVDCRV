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

4.0 VulType

VulType: R03



4.1 RelatedFiles

\- MISSING: Route handler for GET /api/courses/<course\_id>/posts

\- MISSING: Database query logic for searching posts



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Logic handling the 'keyword' query parameter and executing the database search



4.3 RootCause

\- The search endpoint constructs SQL queries by concatenating the 'keyword' input directly into the query string.

\- This lack of input validation or parameterization allows attackers to inject UNION SELECT statements to retrieve data from other tables (e.g., user table).



4.4 ActionablePlan

\- Target File: controllers/post\_controller.py

&nbsp; Target: Search posts function

&nbsp; Change: Replace dynamic SQL string concatenation with parameterized queries (prepared statements) to ensure the 'keyword' is treated strictly as data.



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