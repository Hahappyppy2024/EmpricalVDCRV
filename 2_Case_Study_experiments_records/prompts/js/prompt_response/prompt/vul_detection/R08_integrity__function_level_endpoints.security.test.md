

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


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



### 4) Output Requirements (STRICT)

Your response MUST contain exactly the following 6 sections, in this exact order.
Do NOT add extra sections.
Do NOT output any code diff.

4.0 VulType
Choose exactly ONE from: R01 R02 R03 R04 R05 R06 R07 R08 R09 R10
Format:
VulType: Rxx


4.1 RelatedFiles
List ONLY the concrete file names/paths that are involved in the failing request path (/api/courses).
If a required file is not shown in the provided snippets, write: MISSING: <expected file role>

Format:
RelatedFiles:
- <file_path_1>
- <file_path_2>
- MISSING: <role description>


4.2 RelevantCodeInsideFiles
For EACH file listed in 4.1:
- Identify the exact route / middleware / function related to GET /api/courses
- Quote ONLY the minimal relevant code fragment (no full file dump)

Format:
RelevantCodeInsideFiles:
File: <file_path>
- Route/Middleware/Function: <name>
- Relevant Code:
  <minimal code snippet>

If file is MISSING, write:
File: MISSING
- Needed: <what must be located (e.g., route definition for GET /api/courses)>


4.3 RootCause
2–4 bullets explaining:
- What check is missing or incorrect
- Exactly where it should be enforced (route level / middleware level / controller)

Format:
RootCause:
- ...
- ...


4.4 ActionablePlan
Concrete minimal repair steps.
Each bullet MUST include:
- Target File
- Target Function/Route
- What to change (precise behavior, e.g., “add auth middleware before handler” or “return 401 if no session.user”)

Format:
ActionablePlan:
- Target File: ...
  Target: ...
  Change: ...

- Target File: ...
  Target: ...
  Change: ...


4.5 FileToActionMap
Map files → repair actions clearly.

Format:
FileToActionMap:
- <file_path> → <short description of modification>
- <file_path> → <short description of modification>