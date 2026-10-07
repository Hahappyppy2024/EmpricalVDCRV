\[1] VulnType

VulnType: R01



\[2] TestMapping

TestMapping:



\* (expected: student `s2` `PUT /api/courses/:course\_id/assignments/:assignment\_id/submissions/:submission\_id` for \*\*someone else’s\*\* submission is rejected with \*\*401/403/404\*\* OR returns 2xx only with explicit denial/no-op semantics) -> (observed: request returns \*\*2xx without denial markers\*\*, or returns an unacceptable status like 400) -> (code cause: the update route lacks an ownership/membership authorization check for `submission\_id` against `req.session.user`, or it treats any course member as allowed to edit any submission).

\* (expected: regardless of status, submission content must not change when attacker is not the owner) -> (observed: `after\_content != before\_content` (e.g., becomes `"hacked"`)) -> (code cause: update handler performs the DB update before verifying ownership, or verifies using the wrong field (e.g., compares against assignment creator / course role rather than submission author), allowing IDOR side effects).



\[3] RootCause

RootCause:



\* The submission update endpoint (`PUT /api/courses/:course\_id/assignments/:assignment\_id/submissions/:submission\_id`) does not enforce \*\*submission ownership\*\*: it fails to confirm that the authenticated user is the submission’s author (or otherwise privileged) before permitting updates.

\* The authorization boundary is misplaced or incomplete: the handler reaches the “update submission” DB call without first verifying `(submission\_id belongs to current\_user)` within the same course/assignment scope.

\* Even if the endpoint returns an error sometimes, the absence of a strict pre-update check allows unauthorized side effects (content changes) that the test detects via admin re-read.



\[4] RepairPlan

RepairPlan:



\* Target: route/controller for `PUT /api/courses/:course\_id/assignments/:assignment\_id/submissions/:submission\_id` | Change: before any update, load the submission by `submission\_id` (and confirm it matches the given `course\_id`/`assignment\_id`), then enforce: allow only (a) submission owner, or (b) teacher/assistant/admin if your policy permits; otherwise return \*\*403\*\*.

\* Target: authorization/middleware used for course-scoped endpoints | Change: ensure the update route is behind “require login” and “require course member” checks; still keep per-resource ownership check in the controller.

\* Target: repository/service function that updates submission content | Change: add a defensive condition so the update occurs only when `submission.user\_id == current\_user\_id` (or privileged role); if unauthorized, perform \*\*no DB write\*\* and return a no-op indicator.

\* Target: response behavior of the update route | Change: for unauthorized attempts, return \*\*403\*\* (preferred) with a clear error string (e.g., contains “forbidden”) and do not return 2xx unless you intentionally implement a no-op with explicit denial semantics (but prefer 403 to satisfy the test’s strictness and clarity).



\[5] ReliabilityClaim

ReliabilityClaim: The test has `s2` attempt to update `s1`’s submission and then re-reads the submission as admin to ensure the content did not change, so any 2xx without denial markers or any content change directly indicates missing ownership enforcement on the submission update endpoint.



\[6] RegressionRisks

RegressionRisks:



\* Teacher/assistant grading workflows might legitimately edit submissions; if you add strict “owner-only” updates, recheck privileged-role behavior and ensure only intended roles bypass ownership.

\* If the route currently allows partial updates, adding course/assignment consistency checks could cause 404/403 for mismatched IDs; recheck correct URL construction in clients/tests.



\[7] SelfCheck (YES/NO + Score: + evidence)

SelfCheck:



\* PlanComplete: YES | Score: 1/1 | Evidence: "Plan enforces owner/privileged-role authorization before any DB write and ensures unauthorized attempts return 403 with no side effects, exactly matching the test’s status + side-effect oracles."

\* GroundedTestMapping: YES | Score: 1/1 | Evidence: "Mappings reference the exact update endpoint used in the test and the admin verification read endpoints used to detect side effects."



