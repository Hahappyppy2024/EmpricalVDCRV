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

\### 4.0 VulType



VulType: R04



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: API route/controller for `POST /api/courses/<course\_id>/invites` (invite creation)

\* MISSING: API route/controller for `POST /api/join` (invite redemption / join)

\* MISSING: Data access layer (model/repo) for `invite\_token` table (insert/select/consume)

\* MISSING: DB schema/init that defines table `invite\_token(token\_hash, …)` and any “single-use” fields (e.g., `used\_at`, `used\_by\_user\_id`, `consumed\_at`)

\* MISSING: Crypto utility used to hash tokens (e.g., SHA-256 hex)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: handler for `POST /api/courses/<course\_id>/invites` showing how `invite\_link` and token are generated and how `token\_hash` is written to `invite\_token`



File: MISSING



\* Needed: handler for `POST /api/join` showing how the plaintext token from request JSON is validated (hash compare) and how the token is marked as used / rejected on reuse



File: MISSING



\* Needed: repository/model functions that interact with `invite\_token` (e.g., `create\_invite\_token(course\_id, role, expires\_at)` and `consume\_invite\_token(token\_hash, user\_id)`)



File: MISSING



\* Needed: schema creation for `invite\_token` to confirm `token\_hash` exists and whether there’s a single-use marker (if not, reuse cannot be enforced robustly)



---



\### 4.3 RootCause



RootCause:



\* Invite creation likely stores the token \*\*in plaintext\*\* (or stores a non-SHA256 value) rather than storing only a 64-hex `token\_hash`, causing DB assertions (`token\_hash` regex and “not contain token”) to fail.

\* `/api/join` likely does \*\*not enforce single-use\*\* (token remains valid after first join), so a second user can reuse it and still receives a success response instead of \*\*400/403\*\*.

\* The missing checks must be enforced in the \*\*invite creation controller\*\* (hash-only storage) and in the \*\*join redemption controller/repo\*\* (atomic consume-once logic).



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*Invite creation API controller/route\*\* (MISSING)

&nbsp; Target: `POST /api/courses/<course\_id>/invites`

&nbsp; Change: Generate a random token for the invite link; compute `token\_hash = sha256(token).hexdigest()` (64 hex chars) and store \*\*only\*\* `token\_hash` in `invite\_token`. Return the plaintext token only embedded in `invite\_link` (do not store it anywhere).



\* Target File: \*\*Invite join API controller/route\*\* (MISSING)

&nbsp; Target: `POST /api/join`

&nbsp; Change: Hash the provided token the same way; look up a valid invite by `token\_hash` that is \*\*unused\*\* and \*\*unexpired\*\*. If not found (missing/used/expired) return \*\*400 or 403\*\*. If found, create membership and then \*\*consume\*\* the token so subsequent reuse fails.



\* Target File: \*\*Invite token repository/model\*\* (MISSING)

&nbsp; Target: `consume\_invite\_token(...)` (or equivalent)

&nbsp; Change: Implement single-use enforcement atomically (transaction): select valid row (`used\_at IS NULL` and not expired), then mark as used (`used\_at=now`, `used\_by\_user\_id=current\_user`) or delete row. Ensure join uses this consume operation, not a plain “select then update later” without guarding.



\* Target File: \*\*DB schema/init\*\* (MISSING)

&nbsp; Target: `invite\_token` table definition

&nbsp; Change: Ensure table includes `token\_hash` (TEXT) and a single-use marker (e.g., `used\_at` nullable). Ensure TTL is stored/enforced (e.g., `expires\_at`) since test passes `ttl\_minutes`.



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: `POST /api/courses/<course\_id>/invites` controller → store `sha256(token)` in `invite\_token.token\_hash`; never store plaintext token

\* MISSING: `POST /api/join` controller → validate by hashing request token; deny reuse by consuming token after first successful join

\* MISSING: invite\_token repo/model → implement atomic “find valid + consume once” operation

\* MISSING: schema/init for `invite\_token` → add/verify `token\_hash` + `used\_at`/`expires\_at` fields to support hash-only + single-use enforcement



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
