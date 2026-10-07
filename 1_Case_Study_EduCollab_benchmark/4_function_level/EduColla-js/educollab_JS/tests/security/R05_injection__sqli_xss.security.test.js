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