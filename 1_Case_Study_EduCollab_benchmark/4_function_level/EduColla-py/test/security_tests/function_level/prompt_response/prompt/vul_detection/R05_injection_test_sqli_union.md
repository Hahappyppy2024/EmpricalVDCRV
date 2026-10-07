


You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


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

### 4) Relevant Application Code (ONLY what is pasted exists)
{CODE_SNIPPETS: routes + middleware + controller (+ repo/view if needed)}

### 5) Output Requirements (STRICT)
Your response MUST contain exactly the following 7 sections, in this exact order, with the exact headings.
- Do NOT add extra sections.
- Do NOT omit any section.
- Do NOT output any code diff.

[1] VulnType
Choose exactly ONE label from: R01 R02 R03 R04 R05 R06 R07 R08 R09 R10
Format:
VulnType: Rxx

[2] TestMapping
2–4 bullets mapping: (expected by test) -> (observed) -> (code cause).
Each bullet MUST reference a concrete code point shown (route/middleware/function name).
Format:
TestMapping:
- ...
- ...

[3] RootCause
2–4 bullets pointing to the exact missing/incorrect check and where it should be enforced (route/middleware/controller).
Format:
RootCause:
- ...
- ...

[4] RepairPlan
3–7 bullets. EACH bullet MUST include BOTH:
- Target: <file/snippet name + function/route>
- Change: <exact edit>
Format:
RepairPlan:
- Target: ... | Change: ...
- Target: ... | Change: ...
- Target: ... | Change: ...

[5] ReliabilityClaim
Exactly ONE sentence explaining why this diagnosis is supported by the given test + code (no speculation).
Format:
ReliabilityClaim: ...

[6] RegressionRisks
1–3 bullets listing what might break and what should be rechecked later.
Format:
RegressionRisks:
- ...
- ...

[7] SelfCheck (YES/NO + Score: + evidence)
Format:
SelfCheck:
- PlanComplete: YES/NO | Score: 0/1 | Evidence: "..."
- GroundedTestMapping: YES/NO | Score: 0/1 | Evidence: "..."

Constraints:
- Do NOT invent filenames, tests, or endpoints beyond what is shown.
- Keep the plan minimal and localized.s