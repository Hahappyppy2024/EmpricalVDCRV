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

\### 4.0 VulType



VulType: R01



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: API routes module that defines `POST /api/courses/<course\_id>/assignments`

\* MISSING: Assignments API controller/handler for course assignment creation

\* MISSING: API routes module that defines `GET /api/courses/<course\_id>/posts`

\* MISSING: Posts API controller/handler for listing course posts

\* MISSING: Authentication middleware / session guard used for `/api/\*` endpoints

\* MISSING: Course membership/role authorization helper (must answer “is member?” and “role in course?”)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: route definition + middleware chain for `POST /api/courses/<course\_id>/assignments` (to see whether auth/role checks are applied before creation)



File: MISSING



\* Needed: assignments creation handler (the code that inserts assignment into DB). Must show where requester role is validated (or missing).



File: MISSING



\* Needed: route definition + middleware chain for `GET /api/courses/<course\_id>/posts` (to see whether membership checks are applied before listing)



File: MISSING



\* Needed: posts listing handler (the code that queries posts by course). Must show where requester membership is validated (or missing).



File: MISSING



\* Needed: auth middleware that sets the current user identity for API requests (e.g., `g.user`, `session\['user\_id']`, `req.user\_id`) used by downstream checks.



File: MISSING



\* Needed: membership/role check function(s) used by controllers (e.g., `require\_course\_member(course\_id)` and `require\_course\_role(course\_id, allowed\_roles)`).



---



\### 4.3 RootCause



RootCause:



\* `POST /api/courses/<course\_id>/assignments` either lacks an authentication/authorization gate or only checks “logged in” but not \*\*role\*\*, allowing a `student` member to create assignments (returns 200/201 or other non-401/403).

\* `GET /api/courses/<course\_id>/posts` lacks a \*\*course membership\*\* check, so a logged-in non-member can list posts for an arbitrary `course\_id` (IDOR), returning 200 (or other non-401/403).

\* The enforcement points are wrong/missing at the \*\*route/controller level\*\*: both endpoints must deny access before querying/inserting data.



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*API routes for assignments\*\* (MISSING)

&nbsp; Target: `POST /api/courses/<course\_id>/assignments`

&nbsp; Change: Add authentication guard first (if no session user → return \*\*401\*\*). Then add role enforcement: load membership for `(course\_id, current\_user\_id)` and if role is `student` (or user not in allowed roles), return \*\*403\*\*. Only proceed to create assignment when role is allowed (e.g., `teacher/admin/assistant` per your policy).



\* Target File: \*\*Assignments controller\*\* (MISSING)

&nbsp; Target: assignment creation handler

&nbsp; Change: Defense-in-depth: re-check membership role inside the handler (even if middleware exists). Ensure it never inserts an assignment when the requester is a student; return \*\*403\*\* and do not touch DB.



\* Target File: \*\*API routes for posts\*\* (MISSING)

&nbsp; Target: `GET /api/courses/<course\_id>/posts`

&nbsp; Change: Add authentication guard (no user → \*\*401\*\*) and course isolation guard: if requester is not a member of the course → \*\*403\*\*. Only then query and return posts.



\* Target File: \*\*Posts controller\*\* (MISSING)

&nbsp; Target: posts list handler

&nbsp; Change: Defense-in-depth: verify membership before querying posts; if not a member return \*\*403\*\* with a minimal JSON error.



\* Target File: \*\*Membership/authorization helper\*\* (MISSING)

&nbsp; Target: `get\_membership(course\_id, user\_id)` / `is\_member` / `require\_role`

&nbsp; Change: Implement canonical checks used by both endpoints: (1) membership existence, (2) role-in-course lookup, (3) allowed-roles predicate. Ensure consistent 401 vs 403 behavior.



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: assignments API routes/controller → enforce auth + deny student role on assignment creation (401/403)

\* MISSING: posts API routes/controller → enforce auth + deny non-members from listing course posts (401/403)

\* MISSING: auth middleware/session guard → provide reliable current-user identity for API enforcement

\* MISSING: membership/authorization helper → centralize “is member” + “role allowed” checks used across endpoints



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
