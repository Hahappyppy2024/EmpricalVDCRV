

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


### 2) Function Feature (security property)
Secure invite tokens: course invite tokens must be stored hash-only
(no plaintext) and enforced as single-use, so the first /api/join
succeeds and any reuse is rejected (400/403).



### 3) Failed Security Test
import re
from .._helpers import db_all, db_one, api_post_json, ensure_user_and_membership
import pytest

def _extract_token(invite_link: str) -> str:
    m = re.search(r"token=([^&]+)", invite_link)
    assert m, f"Cannot extract token from {invite_link}"
    return m.group(1)

def _login_html(client, username, password):
    return client.post("/login", data={"username": username, "password": password}, follow_redirects=True)

def _register_html(client, username, password, display_name):
    return client.post("/register", data={"username": username, "password": password, "display_name": display_name}, follow_redirects=True)


def test_crypto_invite_token_is_hash_only_and_single_use(client, app):
    # Admin create course + invite
    _login_html(client, "admin", "admin123")
    client.post("/courses/new", data={"title": "SingleUse Course", "description": "D"}, follow_redirects=True)
    course = db_one(client, "SELECT course_id FROM course WHERE title='SingleUse Course' ORDER BY course_id DESC")
    course_id = course["course_id"]

    rv = api_post_json(client, f"/api/courses/{course_id}/invites", {"role_in_course": "student", "ttl_minutes": 60}, expected_status=(200,))
    invite_link = rv.get_json()["invite_link"]
    token = _extract_token(invite_link)

    # DB should not store plaintext token
    rows = db_all(client, "SELECT token_hash FROM invite_token")
    assert rows, "invite_token table should have at least one row"
    token_hash = rows[-1]["token_hash"]
    assert re.match(r"^[a-f0-9]{64}$", token_hash, re.I)
    assert token_hash != token
    assert token not in str([dict(r) for r in rows])

    # Register bob and join (first use)
    _register_html(client, "bob_sec", "password", "Bob Sec")
    _login_html(client, "bob_sec", "password")
    rv = api_post_json(client, "/api/join", {"token": token}, expected_status=(200,))
    assert rv.get_json()["joined"] is True

    # Register charlie and try reuse (should fail)
    _register_html(client, "charlie_sec", "password", "Charlie Sec")
    _login_html(client, "charlie_sec", "password")
    rv = client.post("/api/join", json={"token": token})
    assert rv.status_code in (400, 403)


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