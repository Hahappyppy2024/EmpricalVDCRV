\### 4.0 VulType



VulType: R01



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: Flask API blueprint/routes file that defines `POST /api/courses/<course\_id>/assignments`

\* MISSING: Assignments API controller/handler that creates assignments

\* MISSING: Flask API blueprint/routes file that defines `GET /api/courses/<course\_id>/posts`

\* MISSING: Posts API controller/handler that lists posts for a course

\* MISSING: Authentication/session helper used by API endpoints to determine current user (e.g., `current\_user`, `session\["user\_id"]`)

\* MISSING: Membership/role authorization helper or repository (course\_id × user\_id → role; member/non-member decision)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: the exact route/middleware chain for `POST /api/courses/<course\_id>/assignments` (to see whether it applies login-required and role checks before inserting)



File: MISSING



\* Needed: the assignment creation handler logic (DB insert path) and any authorization checks inside it (or missing)



File: MISSING



\* Needed: the exact route/middleware chain for `GET /api/courses/<course\_id>/posts` (to see whether it applies login-required and membership checks before querying)



File: MISSING



\* Needed: the posts list handler logic (DB query path) and any membership checks inside it (or missing)



File: MISSING



\* Needed: the functions that resolve current user identity and course membership role (the enforcement point for 401 vs 403 decisions)



---



\### 4.3 RootCause



RootCause:



\* `POST /api/courses/<course\_id>/assignments` exists but does not enforce \*\*role-based authorization\*\*, so a logged-in user with role `student` can reach the creation logic and gets a non-401/403 status.

\* `GET /api/courses/<course\_id>/posts` exists but does not enforce \*\*course membership isolation\*\*, so a logged-in non-member can list posts (IDOR), returning a non-401/403 status.

\* The checks are missing or incorrectly implemented at the \*\*route/controller layer\*\* (and should be shared via a consistent authz helper).



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*Assignments API routes/controller\*\* (MISSING)

&nbsp; Target: `POST /api/courses/<course\_id>/assignments`

&nbsp; Change: Add an authentication guard first: if no authenticated user → return \*\*401\*\*. Then enforce role: look up membership for `(course\_id, current\_user\_id)`; if not a member or role is `"student"` → return \*\*403\*\*; only allowed roles proceed.



\* Target File: \*\*Posts API routes/controller\*\* (MISSING)

&nbsp; Target: `GET /api/courses/<course\_id>/posts`

&nbsp; Change: Add authentication guard (no user → \*\*401\*\*) and membership guard: if requester is not a member of the course → \*\*403\*\*; only then query/return posts.



\* Target File: \*\*Authorization helper / middleware\*\* (MISSING)

&nbsp; Target: shared guards like `require\_login`, `require\_course\_member`, `require\_course\_role(allowed\_roles)`

&nbsp; Change: Centralize the 401/403 logic so both endpoints behave consistently with the test oracle.



\* Target File: \*\*Membership repository/model\*\* (MISSING)

&nbsp; Target: membership lookup used by guards/controllers

&nbsp; Change: Ensure membership lookup matches the roles seeded by `ensure\_user\_and\_membership(..., "student")` (exact role string), and returns “no membership” reliably for outsiders.



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: assignments API route/controller → enforce login + deny student role (401/403) before DB insert

\* MISSING: posts API route/controller → enforce login + deny non-members (401/403) before DB query

\* MISSING: auth/session helper → reliably provide current user id to authorization checks

\* MISSING: membership/authorization helper/repo → implement reusable member/role guards used by both endpoints



