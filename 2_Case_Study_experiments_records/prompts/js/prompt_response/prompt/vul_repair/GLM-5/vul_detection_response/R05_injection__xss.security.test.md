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

4.0 VulType
VulType: R03

4.1 RelatedFiles
- MISSING: Route handler for GET /courses/:course_id/posts/:post_id (HTML rendering)
- MISSING: Template file or view engine logic for rendering post details

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Template or view code responsible for rendering the 'body' of the post

4.3 RootCause
- The server-side rendering logic outputs the post content (body) raw (unescaped) into the HTML response.
- The template engine's auto-escaping feature is disabled or overridden, allowing the injected `<script>` tag to be interpreted by the browser.

4.4 ActionablePlan
- Target File: views/postDetail.html (or relevant template file)
  Target: The variable output for the post body
  Change: Apply HTML escaping filter/function to the post body variable (e.g., `{{body}}` in Handlebars or `<%= body %>` in EJS with escaping enabled) to convert `<` to `&lt;` and `>` to `&gt;`.

- Target File: server.js (or app configuration file)
  Target: View engine configuration
  Change: Ensure the view engine is configured with auto-escaping enabled by default.

4.5 FileToActionMap
- views/postDetail.html → Enable HTML escaping for post content variables.
- server.js → Verify/enable auto-escaping in view engine settings.

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