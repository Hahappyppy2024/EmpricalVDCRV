You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).

### 2) Function Feature (security property)

Role- and membership-based API access control: students cannot create 
assignments and non-members cannot access a course’s post list 
(must return 401/403 when the endpoints exist).


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

\### 4.0 VulType



VulType: R01



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: Flask API blueprint/routes file that defines `POST /api/courses/<course\_id>/assignments`

\* MISSING: Assignments API controller/handler that creates assignments

\* MISSING: Flask API blueprint/routes file that defines `GET /api/courses/<course\_id>/posts`

\* MISSING: Posts API controller/handler that lists posts for a course

\* MISSING: Authentication/session helper used by API endpoints to determine current user (e.g., `current\_user`, `session\["user\_id"]`)

\* MISSING: Membership/role authorization helper or repository (course\_id × user\_id → role; member/non-member decision)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: the exact route/middleware chain for `POST /api/courses/<course\_id>/assignments` (to see whether it applies login-required and role checks before inserting)



File: MISSING



\* Needed: the assignment creation handler logic (DB insert path) and any authorization checks inside it (or missing)



File: MISSING



\* Needed: the exact route/middleware chain for `GET /api/courses/<course\_id>/posts` (to see whether it applies login-required and membership checks before querying)



File: MISSING



\* Needed: the posts list handler logic (DB query path) and any membership checks inside it (or missing)



File: MISSING



\* Needed: the functions that resolve current user identity and course membership role (the enforcement point for 401 vs 403 decisions)



---



\### 4.3 RootCause



RootCause:



\* `POST /api/courses/<course\_id>/assignments` exists but does not enforce \*\*role-based authorization\*\*, so a logged-in user with role `student` can reach the creation logic and gets a non-401/403 status.

\* `GET /api/courses/<course\_id>/posts` exists but does not enforce \*\*course membership isolation\*\*, so a logged-in non-member can list posts (IDOR), returning a non-401/403 status.

\* The checks are missing or incorrectly implemented at the \*\*route/controller layer\*\* (and should be shared via a consistent authz helper).



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*Assignments API routes/controller\*\* (MISSING)

&nbsp; Target: `POST /api/courses/<course\_id>/assignments`

&nbsp; Change: Add an authentication guard first: if no authenticated user → return \*\*401\*\*. Then enforce role: look up membership for `(course\_id, current\_user\_id)`; if not a member or role is `"student"` → return \*\*403\*\*; only allowed roles proceed.



\* Target File: \*\*Posts API routes/controller\*\* (MISSING)

&nbsp; Target: `GET /api/courses/<course\_id>/posts`

&nbsp; Change: Add authentication guard (no user → \*\*401\*\*) and membership guard: if requester is not a member of the course → \*\*403\*\*; only then query/return posts.



\* Target File: \*\*Authorization helper / middleware\*\* (MISSING)

&nbsp; Target: shared guards like `require\_login`, `require\_course\_member`, `require\_course\_role(allowed\_roles)`

&nbsp; Change: Centralize the 401/403 logic so both endpoints behave consistently with the test oracle.



\* Target File: \*\*Membership repository/model\*\* (MISSING)

&nbsp; Target: membership lookup used by guards/controllers

&nbsp; Change: Ensure membership lookup matches the roles seeded by `ensure\_user\_and\_membership(..., "student")` (exact role string), and returns “no membership” reliably for outsiders.



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: assignments API route/controller → enforce login + deny student role (401/403) before DB insert

\* MISSING: posts API route/controller → enforce login + deny non-members (401/403) before DB query

\* MISSING: auth/session helper → reliably provide current user id to authorization checks

\* MISSING: membership/authorization helper/repo → implement reusable member/role guards used by both endpoints



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