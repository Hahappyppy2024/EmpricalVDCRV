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



