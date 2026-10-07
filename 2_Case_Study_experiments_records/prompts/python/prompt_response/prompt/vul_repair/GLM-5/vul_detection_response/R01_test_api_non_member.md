You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).

### 2) Function Feature (security property)
API access control enforcement: students must be forbidden from creating assignments, 
and non-members must be denied access to a course’s posts (returning 401/403 when the endpoints exist).

### 3) Failed Security Test
import os
import io
import pytest

from .._helpers import api_get, api_post_json, ensure_user_and_membership

# ============================================================
# API (JSON) - Access Control (TRUE PASS version)
# ============================================================

def test_api_student_cannot_create_assignment(client, auth, sample_course):
    course_id = sample_course
    ensure_user_and_membership(client, course_id, "student_acl_api", "pass", "Student ACL API", "student")
    auth.login("student_acl_api", "pass")

    rv = client.post(
        f"/api/courses/{course_id}/assignments",
        json={"title": "Should Fail", "description": "no"},
        follow_redirects=False,
    )

    # If endpoint not implemented / method not allowed -> skip (not counted as pass)
    if rv.status_code in (404, 405):
        pytest.skip("API endpoint not implemented for assignment creation")

    # TRUE-PASS oracle: if endpoint exists, student must be rejected
    # (Use 401/403 only; anything else means policy/implementation is unclear or wrong)
    assert rv.status_code in (401, 403), f"Expected 401/403 for student, got {rv.status_code}"

    # Guardrail: if it somehow succeeds, fail loudly (redundant but explicit)
    assert rv.status_code not in (200, 201)


def test_api_non_member_cannot_list_course_posts(client, auth, sample_course):
    course_id = sample_course
    auth.register("outsider_api", "pass", "Out Sider API")
    auth.login("outsider_api", "pass")

    rv = client.get(f"/api/courses/{course_id}/posts", follow_redirects=False)

    if rv.status_code == 404:
        pytest.skip("API endpoint not implemented for listing posts")

    # TRUE-PASS oracle (strict): non-member must be denied
    assert rv.status_code in (401, 403), f"Expected 401/403 for non-member, got {rv.status_code}"


4.0 VulType

VulType: R01



4.1 RelatedFiles

\- MISSING: Route handler for POST /api/courses/:course\_id/assignments

\- MISSING: Route handler for GET /api/courses/:course\_id/posts

\- MISSING: Authorization middleware for role and membership checks



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Route definition and handler for POST /api/courses/:course\_id/assignments



File: MISSING

\- Needed: Route definition and handler for GET /api/courses/:course\_id/posts



4.3 RootCause

\- The endpoint for creating assignments (POST .../assignments) does not validate the user's role within the course, allowing students to perform actions restricted to teachers/admins.

\- The endpoint for listing posts (GET .../posts) fails to check if the authenticated user is a member of the specified course, allowing unauthorized access to course content.



4.4 ActionablePlan

\- Target File: routes/courses.py (or assignments.py)

&nbsp; Target: POST /api/courses/<course\_id>/assignments endpoint

&nbsp; Change: Add authorization logic to verify the current user's role in the course is 'teacher' or 'admin' before processing the request; return 403 if validation fails.



\- Target File: routes/courses.py (or posts.py)

&nbsp; Target: GET /api/courses/<course\_id>/posts endpoint

&nbsp; Change: Add authorization logic to verify the current user is an active member of the course; return 403 if validation fails.



4.5 FileToActionMap

\- routes/courses.py -> Add role verification for assignment creation.

\- routes/courses.py -> Add membership verification for listing posts.

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