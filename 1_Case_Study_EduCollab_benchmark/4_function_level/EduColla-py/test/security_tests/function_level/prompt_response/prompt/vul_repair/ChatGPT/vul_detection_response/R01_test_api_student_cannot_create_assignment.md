\[1] VulnType

VulnType: R01



\[2] TestMapping

TestMapping:



\* (expected: authenticated \*\*student\*\* `POST /api/courses/:course\_id/assignments` is rejected with \*\*401/403\*\* if the endpoint exists) -> (observed: endpoint exists (not 404/405) but returns a status \*\*outside\*\* `\[401, 403]`) -> (code cause: `POST /api/courses/:course\_id/assignments` is missing a role gate that blocks `role\_in\_course = "student"` before the create-assignment logic runs).

\* (expected: authenticated \*\*non-member\*\* `GET /api/courses/:course\_id/posts` is rejected with \*\*401/403\*\* if the endpoint exists) -> (observed: endpoint exists (not 404) but returns a status \*\*outside\*\* `\[401, 403]`) -> (code cause: `GET /api/courses/:course\_id/posts` is missing a membership check scoped to the requested `course\_id` (IDOR), or it checks only “logged in” and not “member of this course”).

\* (expected: API access control returns 401/403 (not redirects) for `/api/\*`) -> (observed: a different status is returned) -> (code cause: API routes are not consistently protected by the same “API-style” auth middleware (login-required + membership + role), so policy is bypassed or returns non-oracle codes.



\[3] RootCause

RootCause:



\* Role enforcement is absent or incorrect on `POST /api/courses/:course\_id/assignments`: the route/controller does not verify course role and deny students.

\* Course isolation enforcement is absent or incorrect on `GET /api/courses/:course\_id/posts`: the route/controller does not verify the caller has membership in that `course\_id`.

\* The correct enforcement point is the route/middleware boundary for these endpoints, using a shared membership/role lookup keyed by `(course\_id, current\_user)`.



\[4] RepairPlan

RepairPlan:



\* Target: route/middleware chain for `POST /api/courses/:course\_id/assignments` | Change: add/ensure “require login” + “require course member” + “require non-student role” checks; return \*\*401\*\* if unauthenticated and \*\*403\*\* if role is `student`.

\* Target: route/middleware chain for `GET /api/courses/:course\_id/posts` | Change: add/ensure “require login” + “require course member (scoped to course\_id)” check; return \*\*401\*\* if unauthenticated and \*\*403\*\* if not a member.

\* Target: membership/role resolution used by these checks | Change: ensure the lookup uses `course\_id` from the URL and the current session user identity; do not treat membership in other courses as sufficient.

\* Target: controllers for assignment creation and post listing (defense-in-depth) | Change: add early guards that refuse requests when membership/role checks fail, even if middleware is mis-mounted.



\[5] ReliabilityClaim

ReliabilityClaim: The tests only fail when the endpoints exist and return a status outside 401/403 for a student creating assignments or an outsider listing posts, which directly indicates missing or ineffective role/membership checks on those two specific API routes.



\[6] RegressionRisks

RegressionRisks:



\* If some API routes currently use 302 redirects for auth failures, switching to 401 for `/api/\*` may break existing clients/tests; recheck API-wide unauth behavior consistency.

\* If admin is intended to bypass membership checks, ensure the membership middleware handles admin explicitly; recheck admin access to posts after enforcing membership for normal users.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan applies the exact oracle required by the tests (401/403) by adding role gating on assignment creation and membership gating on posts listing."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference only concrete endpoints and expectations present in the test: `POST /api/courses/:course\_id/assignments` and `GET /api/courses/:course\_id/posts` with 401/403 when implemented."



