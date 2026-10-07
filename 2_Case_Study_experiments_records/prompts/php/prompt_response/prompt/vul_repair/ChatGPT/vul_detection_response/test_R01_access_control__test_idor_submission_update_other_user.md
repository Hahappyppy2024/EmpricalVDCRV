You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).



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

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.

### 2) Function Feature (security property)

Robust error handling: the API must reject malformed JSON with a 4xx response
(e.g., 400/415/422) and must not leak stack traces, file paths, DB errors, or secrets in the error body.


### 3) Failed Security Test
import re

# ============================================================
# API (JSON) - Mishandling Exceptional Conditions
# ============================================================

def _text(rv):
try:
return rv.get_data(as_text=True)
except Exception:
return (rv.data or b"").decode("utf-8", errors="ignore")


def _no_leak(text):
forbidden = [
r"Traceback", r"Werkzeug", r"Debugger",
r"File \"[^\"]+\.py\"", r"/[A-Za-z0-9_\-./]+\.py", r"\\[A-Za-z0-9_\-\\.]+\.py",
r"sqlite3\.", r"OperationalError", r"SECRET_KEY",
]
for pat in forbidden:
assert re.search(pat, text, re.I) is None, f"Leakage matched /{pat}/"


def test_api_malformed_json_returns_4xx_and_no_leak(client, auth, sample_course):
# Ensure authenticated (some APIs require auth)
auth.register("exc_api_user", "pass", "Exc API User")
auth.login("exc_api_user", "pass")

    # Choose a common JSON endpoint (course creation)
    rv = client.post(
        "/api/courses",
        data='{"title":',
        headers={"Content-Type": "application/json"},
        follow_redirects=False,
    )
    if rv.status_code == 404:
        # API not implemented in this variant
        return

    assert rv.status_code in (400, 415, 422), f"Expected 4xx for malformed JSON, got {rv.status_code}"
    _no_leak(_text(rv))

### 4.0 VulType

VulType: R01

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route/controller for `PUT /api/courses/<course_id>/assignments/<assignment_id>/submissions/<submission_id>` (submission update)
* MISSING: API route/controller for reading a submission as admin (one of: `GET /api/courses/<course_id>/assignments/<assignment_id>/submissions/<submission_id>` or `GET /api/submissions/<submission_id>`)
* MISSING: Submission repository/model (DB update path for submission content)
* MISSING: Authorization helper for submission ownership and course membership (owner check: submission.user_id == current_user_id; plus course membership role check)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route + handler for `PUT /api/courses/<course_id>/assignments/<assignment_id>/submissions/<submission_id>` showing how it:

    * identifies current user
    * loads submission record (including owner user_id)
    * authorizes update (owner-only)
    * performs DB update

File: MISSING

* Needed: route + handler for the submission GET endpoint used in `_get_submission_as_admin(...)` to verify side effects (must show it reads from DB and returns `content_text`)

File: MISSING

* Needed: repository/model update function (SQL update) that writes `content_text` / `content` and whether it scopes by both `submission_id` and `user_id` (to prevent IDOR)

File: MISSING

* Needed: authorization helper(s) that enforce ownership/membership (e.g., `require_login`, `require_course_member`, `require_submission_owner`)

---

### 4.3 RootCause

RootCause:

* The submission update endpoint likely authorizes only by `submission_id` (or course/assignment id) and does **not** verify that the authenticated user owns the submission, enabling IDOR.
* The DB update is probably executed without an owner constraint (e.g., `UPDATE submissions SET ... WHERE submission_id=?`), so even if the handler “checks” superficially, it can still update another student’s row.
* Enforcement must occur in the **update controller** (ownership check before update) and in the **DB update statement** (scope update by `submission_id AND user_id=current_user_id`) as defense-in-depth.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Submissions API route/controller** (MISSING)
  Target: `PUT /api/courses/<course_id>/assignments/<assignment_id>/submissions/<submission_id>`
  Change: Require authentication. Load the submission by `submission_id` (and optionally course_id/assignment_id). If not found return **404**. If found but `submission.user_id != current_user_id`, return **403** (or **401** if you treat as unauth). Do not perform any update when unauthorized.

* Target File: **Submission repository/model** (MISSING)
  Target: DB update method for submission content
  Change: Update with an ownership guard in SQL: update only where `submission_id = ? AND user_id = ?` (and optionally `assignment_id`/`course_id`). Return affected row count; if 0, treat as forbidden/not found and ensure no content change.

* Target File: **Submission read API used by admin** (MISSING)
  Target: `GET /api/.../submissions/<submission_id>` (either course-scoped or global)
  Change: Ensure it returns stable JSON shape that includes `content_text`/`content` so the test can verify no side effects; do not auto-update anything on GET.

* Target File: **Authorization helper** (MISSING)
  Target: shared guard `require_submission_owner(submission_id)` (or equivalent)
  Change: Centralize the owner check so it is consistently applied across any future submission mutation endpoints.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: submissions update controller (`PUT .../submissions/<submission_id>`) → add owner check; return 403/404; no update on unauthorized
* MISSING: submissions repository/model → scope UPDATE by `submission_id AND user_id`; use affected-row count to enforce no-op on unauthorized
* MISSING: submission GET endpoint(s) → ensure predictable JSON and no mutation; support side-effect verification
* MISSING: authz helper → implement reusable “submission ownership” guard used by mutation routes



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