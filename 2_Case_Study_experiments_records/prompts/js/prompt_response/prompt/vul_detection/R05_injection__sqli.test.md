

You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.


### 2) Function Feature (security property)
Robust search handling: the post-search API must safely process malicious keyword inputs (e.g., SQLi payloads) without crashing or leaking database/stack-trace details, returning a controlled 200/400 response.


### 3) Failed Security Test

const { makeAppWithFreshDb } = require('../_helpers');

console.log('SEC TEST VERSION: R05 SQLi+XSS v2');

function getCourseId(res) {
  const b = res?.body || {};
  return b?.course?.course_id ?? b?.course_id ?? b?.course?.id ?? b?.id ?? null;
}

function getPostId(res) {
  const b = res?.body || {};
  return b?.post?.post_id ?? b?.post_id ?? b?.post?.id ?? b?.id ?? null;
}

function short(res) {
  return {
    status: res?.status,
    ct: res?.headers?.['content-type'],
    body: res?.body,
    text: (res?.text || '').slice(0, 200),
  };
}

async function loginOrDie(agent, username, password) {
  const attempts = [
    { path: '/api/login', body: { username, password } },
    { path: '/api/auth/login', body: { username, password } },
    { path: '/api/login', body: { email: username, password } },
    { path: '/api/auth/login', body: { email: username, password } },
  ];
  let last = null;
  for (const a of attempts) {
    const r = await agent.post(a.path).set('Content-Type', 'application/json').send(a.body);
    last = r;
    if ([200, 201, 204, 302].includes(r.status)) return r;
  }
  throw new Error(`Login failed: ${JSON.stringify(short(last), null, 2)}`);
}

async function createCourseOrWarn(agent) {
  const attempts = [
    { path: '/api/courses', body: { title: 'C', description: 'D' } },
    { path: '/api/courses', body: { name: 'C', description: 'D' } },
    { path: '/api/courses', body: { name: 'C', desc: 'D' } },
  ];
  let last = null;
  for (const a of attempts) {
    const r = await agent.post(a.path).set('Content-Type', 'application/json').send(a.body);
    last = r;

    if ([200, 201].includes(r.status)) {
      const course_id = getCourseId(r);
      if (!course_id) throw new Error(`Course created but cannot extract id: ${JSON.stringify(short(r), null, 2)}`);
      return course_id;
    }

    if ([401, 403, 404].includes(r.status)) {
      console.warn(`WARN: course create not permitted/implemented (status=${r.status}). Not testable.`);
      return null;
    }
  }
  throw new Error(`Create course failed unexpectedly: ${JSON.stringify(short(last), null, 2)}`);
}

describe('Security: SQLi + XSS regressions (minimal)', () => {
  test('SQLi probe in search does not crash or leak stack', async () => {
    const { agent } = makeAppWithFreshDb('sec_sqli.db');

    await loginOrDie(agent, 'admin', 'admin123');
    const course_id = await createCourseOrWarn(agent);
    if (!course_id) return;

    // Prefer .query() to avoid double-encoding issues
    const res = await agent
      .get(`/api/courses/${course_id}/search/posts`)
      .query({ keyword: `' OR 1=1 --` });

    // If endpoint not implemented, don't treat as SQLi failure
    if (res.status === 404) {
      console.warn('WARN: search endpoint not implemented (404). Not testable.');
      return;
    }

    expect([200, 400]).toContain(res.status);

    const body = typeof res.body === 'object' && Object.keys(res.body || {}).length
      ? JSON.stringify(res.body)
      : (res.text || '');

    // no obvious sqlite error dump / stack trace
    expect(body.toLowerCase()).not.toMatch(/sqlite|syntax error|stack|traceback|select .* from/i);
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