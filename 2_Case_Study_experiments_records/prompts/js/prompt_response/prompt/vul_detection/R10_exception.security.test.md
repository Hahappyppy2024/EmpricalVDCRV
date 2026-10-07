

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.



### 2) Function Feature (security property)
Error handling hardening: the API must reject malformed JSON with a controlled 400/415 response and must not leak stack traces or internal file paths in the error message.


### 3) Failed Security Test
const { makeAppWithFreshDb } = require('../_helpers');

describe('Security: Exceptional conditions', () => {
  test('malformed JSON returns 400 and does not leak stack/paths', async () => {
    const { agent } = makeAppWithFreshDb('sec_badjson.db');

    const res = await agent
      .post('/api/courses')
      .set('Content-Type', 'application/json')
      .send('{"title":'); // malformed

    expect([400, 415]).toContain(res.status); // 400 preferred
    const body = typeof res.body === 'object' ? JSON.stringify(res.body) : (res.text || '');
    expect(body).not.toMatch(/Traceback|stack|at\s|\/mnt\/|\\src\\|SyntaxError/i);
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