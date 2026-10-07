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

4.0 VulType
VulType: R08

4.1 RelatedFiles
- MISSING: Route handler for POST /api/courses/:course_id/assignments/:assignment_id/grades/import
- MISSING: Controller or service logic for processing grade CSV imports

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Logic that parses the CSV file and updates submission grades

4.3 RootCause
- The grade import endpoint lacks server-side validation to ensure the 'score' field is a valid numeric value before processing the update.
- The system attempts to update the submission record with the invalid input (or interprets it incorrectly) instead of rejecting the row, resulting in `updatedCount > 0`.

4.4 ActionablePlan
- Target File: controllers/gradeController.js (or assignmentController.js)
  Target: Grade import handler function
  Change: Add strict validation logic for each row in the CSV. Check if `score` is a valid number (e.g., `!isNaN(parseFloat(score))`). If validation fails, skip the record update (do not save) so that `updatedCount` remains 0 for invalid inputs.

- Target File: routes/assignments.js
  Target: Grade import route definition
  Change: Ensure the route directs requests to the validated controller logic (no changes needed if logic is internal to controller).

4.5 FileToActionMap
- controllers/gradeController.js → Add numeric validation for the score field in the import loop.

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