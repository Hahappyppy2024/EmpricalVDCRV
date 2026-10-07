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



\* MISSING: API route definitions for `/api/courses/<course\_id>/assignments` (POST)

\* MISSING: API route definitions for `/api/courses/<course\_id>/posts` (GET)

\* MISSING: Assignment creation controller/handler (API)

\* MISSING: Posts list controller/handler (API)

\* MISSING: Authentication middleware/session helper used by API to identify current user

\* MISSING: Membership/role authorization helper or model/repo for memberships (course\_id × user\_id → role)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: the concrete route handler for `POST /api/courses/<course\_id>/assignments`, including any decorators/middleware (auth + role checks) applied to it.



File: MISSING



\* Needed: the concrete route handler for `GET /api/courses/<course\_id>/posts`, including any decorators/middleware (auth + membership checks) applied to it.



File: MISSING



\* Needed: the code that determines “current user” for API requests (e.g., session/cookie → `user\_id`) so authorization checks can be enforced.



File: MISSING



\* Needed: the membership lookup and role logic (e.g., `get\_membership(course\_id, user\_id)` / `get\_role\_in\_course`) used to decide student vs non-student and member vs non-member.



---



\### 4.3 RootCause



RootCause:



\* `POST /api/courses/<course\_id>/assignments` exists but does not enforce \*\*role-based authorization\*\*, allowing a logged-in student member to create assignments (returns something other than 401/403).

\* `GET /api/courses/<course\_id>/posts` exists but does not enforce \*\*course membership isolation\*\*, allowing a logged-in non-member to list posts for an arbitrary course\_id (IDOR; returns something other than 401/403).

\* The missing/incorrect checks must be enforced at the \*\*route/controller boundary\*\* (before any DB insert/query), ideally via reusable middleware/decorators.



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*Assignments API route/controller\*\* (MISSING)

&nbsp; Target: `POST /api/courses/<course\_id>/assignments`

&nbsp; Change: Enforce authentication first (if no authenticated user → return \*\*401\*\*). Then enforce role: load membership for `(course\_id, current\_user\_id)`; if not a member or role is `student` → return \*\*403\*\*; only allowed roles proceed to create.



\* Target File: \*\*Posts API route/controller\*\* (MISSING)

&nbsp; Target: `GET /api/courses/<course\_id>/posts`

&nbsp; Change: Enforce authentication (no user → \*\*401\*\*) and course membership (if requester not a member → \*\*403\*\*) before listing posts.



\* Target File: \*\*Authorization helper / middleware\*\* (MISSING)

&nbsp; Target: reusable guards such as `require\_login`, `require\_course\_member`, `require\_course\_role(allowed\_roles)`

&nbsp; Change: Centralize logic so both endpoints consistently return \*\*401\*\* when unauthenticated and \*\*403\*\* when authenticated but unauthorized.



\* Target File: \*\*Membership repository/model\*\* (MISSING)

&nbsp; Target: membership lookup for `(course\_id, user\_id)`

&nbsp; Change: Ensure membership checks are accurate and based on the same DB/session used by the API; return canonical `role\_in\_course` values that match what `ensure\_user\_and\_membership(..., role)` seeds (e.g., `"student"`).



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: assignments API route/controller → add auth + “deny student role” check; return 401/403 accordingly

\* MISSING: posts API route/controller → add auth + “deny non-member” check; return 401/403 accordingly

\* MISSING: auth/session helper → provide reliable `current\_user\_id` to authorization checks

\* MISSING: membership/authorization helper/repo → implement `require\_course\_member` and `require\_course\_role` used by both endpoints


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
