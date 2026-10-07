4.0 VulType
VulType: R02

4.1 RelatedFiles
- MISSING: Route handler for POST /api/courses/:course_id/invites (invite creation)
- MISSING: Route handler for POST /api/join (invite consumption)
- MISSING: Database schema or model for invite_tokens

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Logic for creating invite tokens and storing them in the database

File: MISSING
- Needed: Logic for verifying invite tokens and marking them as used

4.3 RootCause
- The application stores invite tokens in plaintext in the database instead of storing a cryptographic hash (e.g., SHA-256), violating the secure storage requirement.
- The application fails to invalidate tokens after use, allowing the same token to be redeemed multiple times (lack of single-use enforcement).

4.4 ActionablePlan
- Target File: routes/invites.js (or controller)
  Target: POST /api/courses/:course_id/invites handler
  Change: Generate a random token, hash it (e.g., sha256), and store only the hash in the database. Return the raw token to the client.

- Target File: routes/join.js (or controller)
  Target: POST /api/join handler
  Change: Hash the received token and look it up in the database. If found and valid, complete the join action and delete the token or mark it as used to prevent reuse. Return 403 if the token is invalid or already used.

4.5 FileToActionMap
- routes/invites.js → Implement secure token hashing before storage.
- routes/join.js → Implement token lookup by hash and invalidation after use.