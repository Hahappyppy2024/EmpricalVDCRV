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


### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API router that defines `GET /api/courses/:course_id/search/posts`
* MISSING: Search controller/handler for post search (parses `keyword` query param)
* MISSING: Data access layer / repository that queries posts for a course with a keyword filter (SQL construction point)
* MISSING: Global error handler / response shaping for API exceptions (to prevent sqlite/stack leakage)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route definition for `GET /api/courses/:course_id/search/posts` including any middleware chain and handler name

File: MISSING

* Needed: the search handler code that reads `req.query.keyword` and executes a DB query (this is where SQLi payloads may break query building)

File: MISSING

* Needed: the DB query code that searches posts (look for string-interpolated SQL such as `... WHERE ... LIKE '%${keyword}%'` or concatenation)

File: MISSING

* Needed: API error-handling code path that converts DB errors into a controlled `400` (or safe `200` with empty results) without exposing sqlite errors/stack traces in body

---

### 4.3 RootCause

RootCause:

* The search implementation likely builds SQL using **string concatenation/interpolation** with the raw `keyword` (e.g., `... LIKE '%${keyword}%'`), so a payload like `' OR 1=1 --` triggers a sqlite syntax error or changes query semantics.
* The handler does not catch DB exceptions and/or the app’s error handler returns raw error messages/stack traces (body contains “sqlite”, “syntax error”, “select … from”, “stack/traceback”), failing the “no leak” assertion.
* Enforcement belongs at the **data access layer** (parameterized query) and at the **controller/global error boundary** (return only controlled 200/400 with sanitized error body).

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Search route/controller** (MISSING)
  Target: handler for `GET /api/courses/:course_id/search/posts`
  Change: Validate `keyword` is a string and enforce basic bounds (e.g., max length). Wrap DB call in try/catch; on error return **400** with a generic message (no sqlite/SQL text).

* Target File: **Posts search repository / DB module** (MISSING)
  Target: function that queries posts by course + keyword
  Change: Replace any string-concatenated SQL with a **parameterized query** (placeholders) and bind `%keyword%` as a parameter; never embed keyword directly into SQL text.

* Target File: **Global API error handler** (MISSING)
  Target: Express error middleware (or per-route error handling)
  Change: Ensure unexpected exceptions return a controlled status (400/500 as appropriate) with a **sanitized JSON error** and without leaking DB engine strings, SQL fragments, stack traces, or file paths.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: search route/controller file → validate keyword input; catch DB errors; respond with safe 200/400 and non-leaky body
* MISSING: posts search DB/repository file → parameterize keyword in SQL (no interpolation) to prevent SQLi/syntax crashes
* MISSING: global error handler/app bootstrap → sanitize exception responses so sqlite/stack details are never returned in API bodies


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