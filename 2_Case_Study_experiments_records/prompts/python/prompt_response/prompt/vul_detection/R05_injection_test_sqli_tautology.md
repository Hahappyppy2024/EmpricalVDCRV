

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.



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

### 4) Output Requirements (STRICT)

Your response MUST contain exactly the following 6 sections, in this exact order.
Do NOT add extra sections.
Do NOT output any code diff.

4.0 VulType
Choose exactly ONE from: R01 R02 R03 R04 R05 R06 R07 R08 R09 R10
Format:
VulType: Rxx


4.1 RelatedFiles
List ONLY the concrete file names/paths that are involved in the failing request path (/api/courses).
If a required file is not shown in the provided snippets, write: MISSING: <expected file role>

Format:
RelatedFiles:
- <file_path_1>
- <file_path_2>
- MISSING: <role description>


4.2 RelevantCodeInsideFiles
For EACH file listed in 4.1:
- Identify the exact route / middleware / function related to GET /api/courses
- Quote ONLY the minimal relevant code fragment (no full file dump)

Format:
RelevantCodeInsideFiles:
File: <file_path>
- Route/Middleware/Function: <name>
- Relevant Code:
  <minimal code snippet>

If file is MISSING, write:
File: MISSING
- Needed: <what must be located (e.g., route definition for GET /api/courses)>


4.3 RootCause
2–4 bullets explaining:
- What check is missing or incorrect
- Exactly where it should be enforced (route level / middleware level / controller)

Format:
RootCause:
- ...
- ...


4.4 ActionablePlan
Concrete minimal repair steps.
Each bullet MUST include:
- Target File
- Target Function/Route
- What to change (precise behavior, e.g., “add auth middleware before handler” or “return 401 if no session.user”)

Format:
ActionablePlan:
- Target File: ...
  Target: ...
  Change: ...

- Target File: ...
  Target: ...
  Change: ...


4.5 FileToActionMap
Map files → repair actions clearly.

Format:
FileToActionMap:
- <file_path> → <short description of modification>
- <file_path> → <short description of modification>