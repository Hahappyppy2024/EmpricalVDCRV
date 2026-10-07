You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


### 2) Function Feature (security property)

Input validation in grade import: when importing grades from CSV, non-numeric scores must be rejected (clamped) and must not update any submission records (updatedCount = 0).



### 3) Failed Security Test

const fs = require('fs');
const path = require('path');
const stream = require('stream');
const archiver = require('archiver');
const request = require('supertest');

const { makeAppWithFreshDb, apiLogin, apiCreateCourse, apiCreateAssignment } = require('../_helpers');

function zipBuffer(entries) {
  return new Promise((resolve, reject) => {
    const archive = archiver('zip', { zlib: { level: 9 } });
    const bufs = [];
    const pass = new stream.PassThrough();
    pass.on('data', (d) => bufs.push(d));
    pass.on('end', () => resolve(Buffer.concat(bufs)));
    archive.on('error', reject);
    archive.pipe(pass);
    for (const e of entries) archive.append(e.content, { name: e.name });
    archive.finalize();
  });
}

describe('R08 Integrity: function-level endpoints', () => {
  test('SEC-FL3: grades import clamps non-numeric and does not update', async () => {
    const { app, agent } = makeAppWithFreshDb('r08_sec_fl3.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    expect([200,201]).toContain(c.status);
    const course_id = c.body.course.course_id;

    const roster = Buffer.from("username,display_name,role_in_course\nstu,Student,student\n");
    await agent.post(`/api/courses/${course_id}/members/import`)
      .attach('csv', roster, { filename: 'm.csv', contentType: 'text/csv' });

    const asg = await apiCreateAssignment(agent, course_id);
    expect([200,201]).toContain(asg.status);
    const assignment_id = asg.body.assignment.assignment_id;

    const student = request.agent(app);
    await apiLogin(student, { username: 'stu', password: 'password' });
    const sub = await student.post(`/api/courses/${course_id}/assignments/${assignment_id}/submissions`).send({ content_text: 'my' });
    expect([200,201]).toContain(sub.status);
    const submission_id = sub.body.submission.submission_id;

    const grades = Buffer.from(`submission_id,score,feedback\n${submission_id},not_a_number,ok\n`);
    const imp = await agent.post(`/api/courses/${course_id}/assignments/${assignment_id}/grades/import`)
      .attach('csv', grades, { filename: 'g.csv', contentType: 'text/csv' });
    expect(imp.status).toBe(200);
    expect(imp.body.updatedCount).toBe(0);
  });
});


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


Output Requirements (STRICT):
Your response MUST contain exactly these 3 sections, in this exact order.

[1] Patch
Output EXACTLY ONE unified diff (git diff style). No alternative patches.

[2] Evidence Mapping
Provide 2–4 bullet lines in the form:
- Signal: <failing test / finding> -> Location: <file:function/route> -> Fix: <<=12 words>

[3] FailureModeGuess
failure_mode_guess: "<Regression|ContractDrift|WrongLocalization|NotFixed|BudgetExceeded|NewSecIssue|N/A>"
failure_mode_guess_reason: "<one sentence tied to signals/patch scope>"