\[1] VulnType

VulnType: R01



\[2] TestMapping

TestMapping:



\* (expected: logged-in student `POST /api/courses/:course\_id/assignments` returns \*\*401/403\*\* if route exists) -> (observed: route exists (not 404/405) but returns a status \*\*not\*\* in `\[401, 403]`) -> (code cause: the `POST /api/courses/:course\_id/assignments` route/controller runs without a “forbid student” role check tied to `role\_in\_course`).

\* (expected: logged-in outsider `GET /api/courses/:course\_id/posts` returns \*\*401/403\*\* if route exists) -> (observed: route exists (not 404) but returns a status \*\*not\*\* in `\[401, 403]`) -> (code cause: the `GET /api/courses/:course\_id/posts` route/controller lacks a course-membership guard (IDOR), or checks login only and not membership scoped to `course\_id`).

\* (expected: enforcement is at API boundary, independent of front-end redirects) -> (observed: responses are not 401/403) -> (code cause: middleware uses web-style redirects or no middleware is mounted on these API routes, so policy is not enforced in the JSON endpoints).



\[3] RootCause

RootCause:



\* Missing role-based gate on `POST /api/courses/:course\_id/assignments`: the handler does not verify that the caller’s membership role for that course is permitted to create assignments (student must be denied).

\* Missing course isolation gate on `GET /api/courses/:course\_id/posts`: the handler does not verify the caller is a member of the requested `course\_id` (outsiders must be denied).

\* Access control is not consistently implemented via shared middleware for course-scoped API routes (login + membership + role), leading to endpoints existing but returning success/other codes.



\[4] RepairPlan

RepairPlan:



\* Target: route/middleware chain for `POST /api/courses/:course\_id/assignments` | Change: attach “require login” + “require course member” + “require role not-student” checks; return \*\*401\*\* if not logged in, \*\*403\*\* if role is `student`.

\* Target: route/middleware chain for `GET /api/courses/:course\_id/posts` | Change: attach “require login” + “require course member” check; return \*\*401\*\* if not logged in, \*\*403\*\* if no membership for `course\_id`.

\* Target: membership/role lookup used by these guards (repo/service called by middleware/controller) | Change: ensure lookup is scoped by both `course\_id` and current user identity (so membership in other courses does not grant access).

\* Target: controllers for assignments/posts (defense-in-depth) | Change: add early guard clauses that refuse when membership/role check fails, to prevent accidental bypass if routes are mounted incorrectly.



\[5] ReliabilityClaim

ReliabilityClaim: The tests skip only on 404/405 but otherwise require 401/403 for a student creating assignments and an outsider listing posts, so failing statuses directly indicate that these two concrete API endpoints are missing the required login/membership/role checks.



\[6] RegressionRisks

RegressionRisks:



\* If your API currently returns 302 redirects for unauthenticated requests, changing to 401 on `/api/\*` may affect other tests/clients; recheck API-wide auth behavior consistency.

\* If admin is intended to bypass course membership, ensure the membership guard handles admin explicitly; recheck admin access to posts/assignments.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan adds the exact enforcement the tests assert (401/403) on the two specified endpoints when they exist."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference only the endpoints and outcomes in the provided tests: `/api/courses/:course\_id/assignments` and `/api/courses/:course\_id/posts` with required 401/403."



