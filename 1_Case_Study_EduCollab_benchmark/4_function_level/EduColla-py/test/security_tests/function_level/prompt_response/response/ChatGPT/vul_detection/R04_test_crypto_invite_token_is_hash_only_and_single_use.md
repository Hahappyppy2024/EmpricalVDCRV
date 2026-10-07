\[1] VulnType

VulnType: R04



\[2] TestMapping

TestMapping:



\* (expected: after `POST /api/courses/:course\_id/invites`, DB table `invite\_token` stores only a 64-hex `token\_hash` and \*\*never\*\* stores the plaintext `token`) -> (observed: `token\_hash` is not 64-hex and/or `token` appears in DB rows / `token\_hash == token`) -> (code cause: invite creation logic is persisting the raw token (or a non-hash value) into `invite\_token.token\_hash` (or another column), instead of storing `SHA-256(token)` only).

\* (expected: first `POST /api/join` with `{token}` returns 200 and JSON `{joined: true}`) -> (observed: first join may succeed (or fails if lookup/hash mismatch)) -> (code cause: join handler either validates using plaintext comparison or uses inconsistent hashing between creation and join).

\* (expected: second `POST /api/join` reuse of the same token returns 400/403) -> (observed: second join returns \*\*not\*\* in `(400,403)` (often 200)) -> (code cause: `/api/join` does not enforce single-use (token record not consumed/invalidated after first successful join), so reuse remains valid).



\[3] RootCause

RootCause:



\* Invite token storage is not hash-only: `invite\_token.token\_hash` is not being set to a fixed-length cryptographic hash of the token, or plaintext token data is persisted and can be found in the DB.

\* Single-use enforcement is missing or ineffective: after a successful join, the invite token record remains reusable (no delete / no “used\_at” mark / no atomic consume), allowing the second join to succeed.

\* Token verification in `/api/join` is not consistently performed as `hash(presented\_token) == token\_hash` with “unused” constraint, causing either acceptance without constraints or inconsistent behavior.



\[4] RepairPlan

RepairPlan:



\* Target: route/controller for `POST /api/courses/:course\_id/invites` | Change: generate a random token, compute `token\_hash = SHA-256(token)` (64 hex), store \*\*only\*\* `token\_hash` in `invite\_token.token\_hash`, and return the plaintext token only inside `invite\_link` (never store plaintext).

\* Target: route/controller for `POST /api/join` | Change: hash the incoming `token` the same way and look up the invite row by `token\_hash`; if no match (or expired/used), return \*\*400/403\*\*.

\* Target: `/api/join` token consumption logic | Change: after a successful join, \*\*invalidate\*\* the invite token (delete the row or mark it used) so any reuse returns \*\*400/403\*\*.

\* Target: DB transaction boundaries for join | Change: make “validate token + consume token + add membership” atomic (single transaction) so two concurrent joins cannot both succeed.

\* Target: `/api/join` success response | Change: ensure JSON response includes `{"joined": true}` on first success, matching the test’s expectation.



\[5] ReliabilityClaim

ReliabilityClaim: The test explicitly inspects `invite\_token.token\_hash` for a 64-hex hash and absence of plaintext token, then performs two `/api/join` requests expecting accept-then-reject behavior, so failure directly indicates incorrect hash-only storage and/or missing single-use consumption in the invite/join endpoints.



\[6] RegressionRisks

RegressionRisks:



\* If the product previously allowed multi-use invites, enforcing single-use will change behavior; recheck any workflows that expect sharing one invite link with many users.

\* If you adopt `SameSite`/CSRF protections elsewhere, ensure `/api/join` still works with HTML-session login used in this test flow.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan satisfies both asserted properties: DB hash-only storage (`token\_hash` 64-hex, no plaintext) and single-use join (first 200 joined=true, second 400/403)."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference only concrete artifacts in the test: table `invite\_token(token\_hash)`, endpoints `/api/courses/:id/invites` and `/api/join`, and the exact status/JSON assertions."



