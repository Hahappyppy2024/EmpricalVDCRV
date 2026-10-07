\### 4.0 VulType



VulType: R01



---



\### 4.1 RelatedFiles



RelatedFiles:



\* MISSING: API route definitions for `/api/courses/<course\_id>/assignments` (POST)

\* MISSING: API route definitions for `/api/courses/<course\_id>/posts` (GET)

\* MISSING: Assignment creation controller/handler (API)

\* MISSING: Posts list controller/handler (API)

\* MISSING: Authentication middleware/session helper used by API to identify current user

\* MISSING: Membership/role authorization helper or model/repo for memberships (course\_id × user\_id → role)



---



\### 4.2 RelevantCodeInsideFiles



RelevantCodeInsideFiles:

File: MISSING



\* Needed: the concrete route handler for `POST /api/courses/<course\_id>/assignments`, including any decorators/middleware (auth + role checks) applied to it.



File: MISSING



\* Needed: the concrete route handler for `GET /api/courses/<course\_id>/posts`, including any decorators/middleware (auth + membership checks) applied to it.



File: MISSING



\* Needed: the code that determines “current user” for API requests (e.g., session/cookie → `user\_id`) so authorization checks can be enforced.



File: MISSING



\* Needed: the membership lookup and role logic (e.g., `get\_membership(course\_id, user\_id)` / `get\_role\_in\_course`) used to decide student vs non-student and member vs non-member.



---



\### 4.3 RootCause



RootCause:



\* `POST /api/courses/<course\_id>/assignments` exists but does not enforce \*\*role-based authorization\*\*, allowing a logged-in student member to create assignments (returns something other than 401/403).

\* `GET /api/courses/<course\_id>/posts` exists but does not enforce \*\*course membership isolation\*\*, allowing a logged-in non-member to list posts for an arbitrary course\_id (IDOR; returns something other than 401/403).

\* The missing/incorrect checks must be enforced at the \*\*route/controller boundary\*\* (before any DB insert/query), ideally via reusable middleware/decorators.



---



\### 4.4 ActionablePlan



ActionablePlan:



\* Target File: \*\*Assignments API route/controller\*\* (MISSING)

&nbsp; Target: `POST /api/courses/<course\_id>/assignments`

&nbsp; Change: Enforce authentication first (if no authenticated user → return \*\*401\*\*). Then enforce role: load membership for `(course\_id, current\_user\_id)`; if not a member or role is `student` → return \*\*403\*\*; only allowed roles proceed to create.



\* Target File: \*\*Posts API route/controller\*\* (MISSING)

&nbsp; Target: `GET /api/courses/<course\_id>/posts`

&nbsp; Change: Enforce authentication (no user → \*\*401\*\*) and course membership (if requester not a member → \*\*403\*\*) before listing posts.



\* Target File: \*\*Authorization helper / middleware\*\* (MISSING)

&nbsp; Target: reusable guards such as `require\_login`, `require\_course\_member`, `require\_course\_role(allowed\_roles)`

&nbsp; Change: Centralize logic so both endpoints consistently return \*\*401\*\* when unauthenticated and \*\*403\*\* when authenticated but unauthorized.



\* Target File: \*\*Membership repository/model\*\* (MISSING)

&nbsp; Target: membership lookup for `(course\_id, user\_id)`

&nbsp; Change: Ensure membership checks are accurate and based on the same DB/session used by the API; return canonical `role\_in\_course` values that match what `ensure\_user\_and\_membership(..., role)` seeds (e.g., `"student"`).



---



\### 4.5 FileToActionMap



FileToActionMap:



\* MISSING: assignments API route/controller → add auth + “deny student role” check; return 401/403 accordingly

\* MISSING: posts API route/controller → add auth + “deny non-member” check; return 401/403 accordingly

\* MISSING: auth/session helper → provide reliable `current\_user\_id` to authorization checks

\* MISSING: membership/authorization helper/repo → implement `require\_course\_member` and `require\_course\_role` used by both endpoints



