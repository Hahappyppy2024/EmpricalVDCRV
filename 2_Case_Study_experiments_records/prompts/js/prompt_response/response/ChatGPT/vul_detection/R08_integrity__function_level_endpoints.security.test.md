### 4.0 VulType

VulType: R08

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route/controller for `POST /api/courses/:course_id/assignments/:assignment_id/grades/import`
* MISSING: CSV parsing/import utility used by grades import (handles multipart `attach('csv', ...)`)
* MISSING: Submissions/grades data access layer that updates submission records (where `score` is written)
* MISSING: API route/controller for `POST /api/courses/:course_id/members/import` (sets up roster; not the failure focus but part of test setup)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route definition + handler for `POST /api/courses/:course_id/assignments/:assignment_id/grades/import`, including how it reads CSV rows and computes `updatedCount`

File: MISSING

* Needed: the code that parses the `score` column and decides whether to update a submission (look for `parseFloat`, `Number(...)`, or direct DB update without validation)

File: MISSING

* Needed: the DB update statement for submissions/grades (e.g., `UPDATE submissions SET score = ? ... WHERE submission_id = ?`), and how the code increments `updatedCount`

File: MISSING

* Needed: any clamping/validation logic (range checks, numeric checks) currently applied (or missing) before writing to DB

---

### 4.3 RootCause

RootCause:

* The grades import path likely treats `score` as a string and still updates the submission (or coerces `not_a_number` into `NaN` / `0` and updates anyway), so `updatedCount` becomes `1` instead of `0`.
* Missing input validation: the importer does not strictly verify `score` is a finite numeric value before performing the DB update.
* Even if validation exists, it’s applied **after** updating (or `updatedCount` is incremented regardless of whether the update was skipped), so the response reports updates when it should not.
* Enforcement must happen inside the **grades import controller/importer** before any DB write and before incrementing `updatedCount`.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Grades import route/controller** (MISSING)
  Target: `POST /api/courses/:course_id/assignments/:assignment_id/grades/import`
  Change: For each CSV row, parse `score` strictly (e.g., `Number(scoreStr)`), and **only proceed** if it is a finite number; otherwise **skip the row** and do not update DB. Ensure the response `updatedCount` counts only rows that actually updated a submission.

* Target File: **CSV import/parsing helper** (MISSING)
  Target: row parsing for `submission_id, score, feedback`
  Change: Normalize/trim inputs; treat empty/invalid numeric strings as invalid. Do not “default” invalid scores to `0`/`NaN`. Optionally collect `skippedCount` but at minimum keep `updatedCount` correct.

* Target File: **Submissions/grades data access layer** (MISSING)
  Target: function performing the update (e.g., `updateSubmissionGrade(submission_id, score, feedback)`)
  Change: Add a guard (defense-in-depth): reject non-finite `score` at the DAO boundary and return “no-op” so controller can keep `updatedCount` unchanged.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: grades import controller/route → validate numeric `score`; skip invalid rows; ensure `updatedCount` increments only on real updates
* MISSING: CSV parsing/import utility → strict numeric parsing; no coercion/defaulting for invalid scores
* MISSING: submissions/grades repository/DAO → enforce finite numeric score before DB update (no-op on invalid)
