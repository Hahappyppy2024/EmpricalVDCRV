### 4.0 VulType

VulType: R01

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route/controller for `PUT /api/courses/<course_id>/assignments/<assignment_id>/submissions/<submission_id>` (submission update)
* MISSING: API route/controller for reading a submission as admin (one of: `GET /api/courses/<course_id>/assignments/<assignment_id>/submissions/<submission_id>` or `GET /api/submissions/<submission_id>`)
* MISSING: Submission repository/model (DB update path for submission content)
* MISSING: Authorization helper for submission ownership and course membership (owner check: submission.user_id == current_user_id; plus course membership role check)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route + handler for `PUT /api/courses/<course_id>/assignments/<assignment_id>/submissions/<submission_id>` showing how it:

  * identifies current user
  * loads submission record (including owner user_id)
  * authorizes update (owner-only)
  * performs DB update

File: MISSING

* Needed: route + handler for the submission GET endpoint used in `_get_submission_as_admin(...)` to verify side effects (must show it reads from DB and returns `content_text`)

File: MISSING

* Needed: repository/model update function (SQL update) that writes `content_text` / `content` and whether it scopes by both `submission_id` and `user_id` (to prevent IDOR)

File: MISSING

* Needed: authorization helper(s) that enforce ownership/membership (e.g., `require_login`, `require_course_member`, `require_submission_owner`)

---

### 4.3 RootCause

RootCause:

* The submission update endpoint likely authorizes only by `submission_id` (or course/assignment id) and does **not** verify that the authenticated user owns the submission, enabling IDOR.
* The DB update is probably executed without an owner constraint (e.g., `UPDATE submissions SET ... WHERE submission_id=?`), so even if the handler “checks” superficially, it can still update another student’s row.
* Enforcement must occur in the **update controller** (ownership check before update) and in the **DB update statement** (scope update by `submission_id AND user_id=current_user_id`) as defense-in-depth.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Submissions API route/controller** (MISSING)
  Target: `PUT /api/courses/<course_id>/assignments/<assignment_id>/submissions/<submission_id>`
  Change: Require authentication. Load the submission by `submission_id` (and optionally course_id/assignment_id). If not found return **404**. If found but `submission.user_id != current_user_id`, return **403** (or **401** if you treat as unauth). Do not perform any update when unauthorized.

* Target File: **Submission repository/model** (MISSING)
  Target: DB update method for submission content
  Change: Update with an ownership guard in SQL: update only where `submission_id = ? AND user_id = ?` (and optionally `assignment_id`/`course_id`). Return affected row count; if 0, treat as forbidden/not found and ensure no content change.

* Target File: **Submission read API used by admin** (MISSING)
  Target: `GET /api/.../submissions/<submission_id>` (either course-scoped or global)
  Change: Ensure it returns stable JSON shape that includes `content_text`/`content` so the test can verify no side effects; do not auto-update anything on GET.

* Target File: **Authorization helper** (MISSING)
  Target: shared guard `require_submission_owner(submission_id)` (or equivalent)
  Change: Centralize the owner check so it is consistently applied across any future submission mutation endpoints.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: submissions update controller (`PUT .../submissions/<submission_id>`) → add owner check; return 403/404; no update on unauthorized
* MISSING: submissions repository/model → scope UPDATE by `submission_id AND user_id`; use affected-row count to enforce no-op on unauthorized
* MISSING: submission GET endpoint(s) → ensure predictable JSON and no mutation; support side-effect verification
* MISSING: authz helper → implement reusable “submission ownership” guard used by mutation routes
