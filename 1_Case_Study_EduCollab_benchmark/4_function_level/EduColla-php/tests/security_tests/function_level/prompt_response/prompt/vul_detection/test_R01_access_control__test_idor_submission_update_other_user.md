

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


### 2) Function Feature (security property)
IDOR prevention: a student must not be able to modify another 
student’s assignment submission via the update API, and any unauthorized
update attempt must be rejected and cause no data change.


### 3) Failed Security Test
import pytest
from common import (
    api_login_admin,
    api_register_and_login_student,
    api_create_course,
    api_add_member,
    api_create_assignment,
    api_submit_assignment,
    BASE_URL,
)


def _get_submission_as_admin(admin, course_id, assignment_id, submission_id):
    """Try common read endpoints. Skip if none exists."""
    candidates = [
        f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}",
        f"{BASE_URL}/api/submissions/{submission_id}",
    ]
    for url in candidates:
        r = admin.get(url, timeout=10)
        if r.status_code == 200:
            try:
                return r.json(), url
            except Exception:
                pytest.fail(f"Submission GET returned non-JSON at {url}: head={r.text[:200]}")
        if r.status_code in (404, 405):
            continue
    pytest.fail("No readable submission GET endpoint found; cannot verify IDOR side-effects.")


def _extract_content(payload):
    """Best-effort extraction across different response shapes."""
    if not isinstance(payload, dict):
        return None
    if "submission" in payload and isinstance(payload["submission"], dict):
        sub = payload["submission"]
        return sub.get("content_text") or sub.get("content")
    return payload.get("content_text") or payload.get("content")


def test_student_cannot_update_other_students_submission_strict():
    admin = api_login_admin()
    course = api_create_course(admin, title="idor-course")

    s1, u1 = api_register_and_login_student()
    s2, u2 = api_register_and_login_student()

    course_id = int(course["course_id"])
    api_add_member(admin, course_id, int(u1["user_id"]), "student")
    api_add_member(admin, course_id, int(u2["user_id"]), "student")

    a = api_create_assignment(admin, course_id, title="A1")
    assignment_id = int(a["assignment_id"])

    sub = api_submit_assignment(s1, course_id, assignment_id, content_text="from s1")
    submission_id = int(sub["submission_id"])

    # Baseline: read original state (prevents false pass)
    before_json, read_url = _get_submission_as_admin(admin, course_id, assignment_id, submission_id)
    before_content = _extract_content(before_json)
    if before_content is None:
        pytest.fail("No readable submission GET endpoint found; cannot verify IDOR side-effects.")
    update_url = f"{BASE_URL}/api/courses/{course_id}/assignments/{assignment_id}/submissions/{submission_id}"
    r = s2.put(update_url, json={"content_text": "hacked"}, timeout=10)

    # 1) Status code strictness
    # Expected: reject with 401/403/404.
    # If returns 2xx, treat as suspicious unless body clearly says no permission/no-op.
    if r.status_code in (401, 403, 404):
        pass
    elif r.status_code in (200, 201, 204):
        # allow 2xx only if response semantics clearly indicate denial/no-op
        body = (r.text or "").lower()
        # Some APIs return JSON {"error":"forbidden"} with 200 (bad but happens).
        # We accept only if body contains clear denial keywords.
        denial_markers = ["forbidden", "unauthorized", "not allowed", "denied", "no permission"]
        if not any(m in body for m in denial_markers):
            pytest.fail(
                "Suspicious: update returned 2xx without explicit denial semantics. "
                f"status={r.status_code} body_head={(r.text or '')[:300]!r}"
            )
    else:
        # Do NOT accept 400 as pass — it can hide auth bypass behind validation errors.
        pytest.fail(f"Unexpected status={r.status_code} body_head={(r.text or '')[:300]!r}")

    # 2) Side-effect strictness: content must NOT change
    after_json, _ = _get_submission_as_admin(admin, course_id, assignment_id, submission_id)
    after_content = _extract_content(after_json)

    assert after_content == before_content, (
        "IDOR vulnerability: other student's submission content changed.\n"
        f"before={before_content!r}\n"
        f"after={after_content!r}\n"
        f"update_status={r.status_code} update_body_head={(r.text or '')[:200]!r}"
    )

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
- Keep the plan minimal and localized.