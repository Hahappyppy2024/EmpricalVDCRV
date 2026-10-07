You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).
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


4.0 VulType

VulType: R02



4.1 RelatedFiles

\- MISSING: Route handler for POST /api/courses/<course\_id>/invites

\- MISSING: Route handler for POST /api/join

\- MISSING: Database model for invite\_token



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Logic for generating and storing invite tokens



File: MISSING

\- Needed: Logic for validating and consuming invite tokens



4.3 RootCause

\- The invite creation endpoint stores the raw token in the database instead of a cryptographic hash (e.g., SHA-256), exposing sensitive credentials to database access risks.

\- The join endpoint lacks logic to track token usage, failing to invalidate the token after the first successful redemption, allowing multiple uses.



4.4 ActionablePlan

\- Target File: routes/invite\_routes.py (or controller)

&nbsp; Target: POST /api/courses/<course\_id>/invites handler

&nbsp; Change: Generate a random token, compute its SHA-256 hash, and store only the hash in the database. Return the raw token to the user once.



\- Target File: routes/join\_routes.py (or controller)

&nbsp; Target: POST /api/join handler

&nbsp; Change: Hash the incoming token with SHA-256 and query the database for the matching hash. If found, check if it has already been used. If not used, complete the join and mark the token as used (delete or set a flag); otherwise, return 400/403.



4.5 FileToActionMap

\- routes/invite\_routes.py → Implement secure token hashing before storage.

\- routes/join\_routes.py → Implement single-use validation logic using the token hash.

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