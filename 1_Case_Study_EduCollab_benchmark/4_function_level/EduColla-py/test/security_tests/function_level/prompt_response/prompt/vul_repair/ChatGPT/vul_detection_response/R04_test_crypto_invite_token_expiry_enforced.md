\[1] VulnType

VulnType: R04



\[2] TestMapping

TestMapping:



\* (expected: `POST /api/courses/:course\_id/invites` returns an `invite\_link` containing `token=...`) -> (observed: token extracted successfully, so invite creation exists) -> (code cause: invite creation route is implemented and stores an invite record in `invite\_token` with an `expires\_at` field).

\* (expected: after DB is forced to `expires\_at = now - 10 minutes`, `POST /api/join` with that token returns \*\*400/403\*\*) -> (observed: join returns a status \*\*not\*\* in `(400, 403)` (commonly 200/201)) -> (code cause: `/api/join` validates token existence but does \*\*not\*\* enforce `expires\_at` (or compares timestamps incorrectly / ignores the DB field), so expired tokens are still accepted).

\* (expected: expiry enforcement uses the DB’s `expires\_at` for the specific token) -> (observed: updating `invite\_token.expires\_at` globally does not affect join outcome) -> (code cause: join logic either does not read `expires\_at` at all, or uses a different table/column name than `invite\_token.expires\_at`, or checks TTL\_minutes rather than the persisted expiry timestamp).



\[3] RootCause

RootCause:



\* The join endpoint (`POST /api/join`) is missing an expiration check against the invite record’s `expires\_at` before granting membership.

\* The expiration comparison is incorrect or bypassed (e.g., string comparison vs datetime, timezone mismatch, or using a different persistence field), so a DB-forced expired token is still treated as valid.

\* The correct enforcement point is the `/api/join` controller (or its invite-token lookup/repo), immediately after resolving the token record and before adding the membership.



\[4] RepairPlan

RepairPlan:



\* Target: controller/handler for `POST /api/join` | Change: after finding the invite record for the provided token, compare `expires\_at` to “now”; if `expires\_at <= now`, return \*\*400\*\* (or \*\*403\*\*) and do not add membership.

\* Target: invite-token repository/query used by `/api/join` | Change: ensure the query retrieves `expires\_at` from the `invite\_token` table/row corresponding to the token (not a different table/field); keep the check scoped to the specific token record.

\* Target: datetime parsing/comparison logic in `/api/join` | Change: parse DB timestamps into a comparable datetime consistently (same timezone/format) and use a single source of truth for “now” (server-side), avoiding string-only comparisons.

\* Target: membership insertion path triggered by `/api/join` | Change: guard membership creation behind the expiry check so expired tokens never cause side effects (no partial insert / no audit “joined” event).

\* Target: error response for expired token | Change: return a controlled error JSON (generic) with status \*\*400/403\*\*, not a redirect.



\[5] ReliabilityClaim

ReliabilityClaim: The test forcibly backdates `invite\_token.expires\_at` in the database and then asserts `POST /api/join` must return 400/403, so a non-400/403 outcome directly demonstrates that `/api/join` is not enforcing the stored expiry timestamp.



\[6] RegressionRisks

RegressionRisks:



\* If your system previously allowed “never-expire” invites, enforcing `expires\_at` universally may reject legacy rows with NULL/empty expiry; recheck how missing `expires\_at` should be handled.

\* If you later add single-use enforcement, ensure expiry checks still run before consumption; recheck join behavior under concurrent requests.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan adds the exact missing expiry gate on `/api/join` using `invite\_token.expires\_at`, which is what the test manipulates."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference only the tested endpoints (`/api/courses/:id/invites`, `/api/join`) and the concrete DB field the test updates (`invite\_token.expires\_at`)."



