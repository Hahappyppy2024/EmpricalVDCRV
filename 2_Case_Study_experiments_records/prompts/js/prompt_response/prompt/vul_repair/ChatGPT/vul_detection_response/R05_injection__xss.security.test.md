You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).

### 2) Function Feature (security property)
XSS protection: user-generated post content must be HTML-escaped on the server-rendered post detail page so stored <script> payloads are not executed (rendered as &lt;script&gt;…)


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
  test('stored XSS is escaped on HTML post detail page', async () => {
    const { agent } = makeAppWithFreshDb('sec_xss.db');

    await loginOrDie(agent, 'admin', 'admin123');
    const course_id = await createCourseOrWarn(agent);
    if (!course_id) return;

    const payload = `<script>alert("x")</script>`;

    const create = await agent
      .post(`/api/courses/${course_id}/posts`)
      .set('Content-Type', 'application/json')
      .send({ title: 't', body: payload });

    if (create.status === 404) {
      console.warn('WARN: create post endpoint not implemented (404). Not testable.');
      return;
    }

    expect([200, 201]).toContain(create.status);

    const post_id = getPostId(create);
    if (!post_id) throw new Error(`Post created but cannot extract post_id: ${JSON.stringify(short(create), null, 2)}`);

    const html = await agent.get(`/courses/${course_id}/posts/${post_id}`);

    // If HTML route not implemented / needs different path, treat as not testable
    if (html.status === 404) {
      console.warn('WARN: HTML post detail page not implemented (404). Not testable.');
      return;
    }

    expect([200, 302]).toContain(html.status);

    // If redirected, we can't inspect body safely
    if (html.status === 302) {
      console.warn('WARN: HTML post detail redirected (302). Not testable for XSS rendering.');
      return;
    }

    // should not contain raw script tag
    expect(html.text).not.toContain(payload);
    // should contain escaped markers
    expect(html.text).toMatch(/&lt;script&gt;|&lt;\/script&gt;/);
  });
});


### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route/controller for `POST /api/courses/:course_id/posts` (stores post body)
* MISSING: Web (HTML) route/controller for `GET /courses/:course_id/posts/:post_id` (renders post detail page)
* MISSING: Template/view file for post detail rendering (server-rendered HTML)
* MISSING: Data access layer for fetching a post’s title/body for HTML rendering (posts repository/model)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: handler for `POST /api/courses/:course_id/posts` to confirm post body is stored “as-is” (the payload `<script>...</script>` ends up persisted)

File: MISSING

* Needed: handler for `GET /courses/:course_id/posts/:post_id` that loads the post record and passes `post.body` into a template

File: MISSING

* Needed: the post detail template code where the body is inserted into HTML (e.g., something like `<div>${post.body}</div>` or template syntax that disables escaping)

File: MISSING

* Needed: DB query for fetching the post record used in the HTML page (so we can see if it returns raw body and where escaping should occur)

---

### 4.3 RootCause

RootCause:

* The HTML post detail page renders user-controlled `post.body` **without HTML escaping**, so the stored payload `<script>alert("x")</script>` appears raw in the response body.
* The template likely uses an “unescaped/raw HTML” output mode (e.g., EJS `<%- ... %>`, Handlebars triple-stash `{{{...}}}`, or manual string concatenation), bypassing default escaping.
* Enforcement must occur at the **server-rendered HTML boundary** (template rendering / view-model preparation), ensuring `post.body` is escaped before being included in the HTML response.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **HTML route/controller for post detail** (MISSING)
  Target: `GET /courses/:course_id/posts/:post_id`
  Change: Ensure the value passed into the template for the post body is HTML-escaped (either escape in controller before rendering, or rely on template default escaping by avoiding any “raw HTML” render path).

* Target File: **Post detail template/view** (MISSING)
  Target: the template expression that outputs `post.body`
  Change: Replace any raw/unescaped output syntax with escaped output syntax (i.e., do not use raw HTML insertion). The resulting HTML must contain `&lt;script&gt;` rather than `<script>`.

* Target File: **Shared HTML escaping utility** (optional but minimal) (MISSING if not present)
  Target: helper used by templates/controllers
  Change: Add/ensure a standard HTML escape routine (escapes `& < > " '`) and use it consistently for user-generated fields rendered into HTML.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: web route/controller for `/courses/:course_id/posts/:post_id` → escape post body before rendering the template (or ensure template auto-escapes)
* MISSING: post detail template/view → stop rendering `post.body` as raw HTML; output escaped text so `<script>` becomes `&lt;script&gt;`
* MISSING: optional escape helper → provide reusable HTML escaping for user-controlled fields rendered server-side


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