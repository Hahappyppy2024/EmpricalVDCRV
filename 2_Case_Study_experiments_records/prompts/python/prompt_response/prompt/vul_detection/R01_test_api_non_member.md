

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.



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