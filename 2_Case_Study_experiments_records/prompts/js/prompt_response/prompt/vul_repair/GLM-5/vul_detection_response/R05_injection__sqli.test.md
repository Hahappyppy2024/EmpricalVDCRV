You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


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

4.0 VulType
VulType: R03

4.1 RelatedFiles
- MISSING: Route handler for GET /api/courses/:course_id/search/posts
- MISSING: Database query logic for post search

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Search logic handling the 'keyword' query parameter

4.3 RootCause
- The application constructs SQL queries by concatenating the user-supplied 'keyword' parameter, making it vulnerable to SQL Injection.
- The application lacks proper error handling, causing raw database errors (e.g., SQLite syntax errors) or stack traces to be returned in the response body when a malformed query is executed.

4.4 ActionablePlan
- Target File: controllers/searchController.js (or relevant controller)
  Target: Post search function
  Change: Replace raw SQL concatenation with parameterized queries (prepared statements) to safely handle the 'keyword' input.

- Target File: app.js (or error handling middleware)
  Target: Global error handler
  Change: Implement a global error handling middleware that catches unhandled exceptions and returns a generic error response (e.g., 500 Internal Server Error) without exposing stack traces or database error details.

4.5 FileToActionMap
- controllers/searchController.js → Fix SQL injection via parameterized queries.
- app.js → Add generic error handling to prevent information leakage.

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