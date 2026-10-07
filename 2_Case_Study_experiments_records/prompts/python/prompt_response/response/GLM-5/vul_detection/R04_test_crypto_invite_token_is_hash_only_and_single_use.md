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

