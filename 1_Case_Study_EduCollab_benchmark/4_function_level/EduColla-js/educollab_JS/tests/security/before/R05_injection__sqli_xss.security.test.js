
const { makeAppWithFreshDb, apiLogin, apiCreateCourse } = require('../_helpers');

describe('Security: SQLi + XSS regressions (minimal)', () => {
  test('SQLi probe in search does not crash or leak stack', async () => {
    const { agent } = makeAppWithFreshDb('sec_sqli.db');
    await apiLogin(agent, 'admin', 'admin123');
    const c = await apiCreateCourse(agent, 'C', 'D');
    const course_id = c.body.course.course_id;

    // probe keyword that could break naive string concat
    const kw = encodeURIComponent(`' OR 1=1 --`);
    const res = await agent.get(`/api/courses/${course_id}/search/posts?keyword=${kw}`);
    expect([200, 400]).toContain(res.status);
    const body = typeof res.body === 'object' ? JSON.stringify(res.body) : (res.text || '');
    // no obvious sqlite error dump
    expect(body.toLowerCase()).not.toMatch(/sqlite|syntax error|stack/i);
  });

  test('stored XSS is escaped on HTML post detail page', async () => {
    const { agent } = makeAppWithFreshDb('sec_xss.db');
    await apiLogin(agent, 'admin', 'admin123');
    const c = await apiCreateCourse(agent, 'C', 'D');
    const course_id = c.body.course.course_id;

    const payload = `<script>alert("x")</script>`;
    const create = await agent.post(`/api/courses/${course_id}/posts`).send({ title: 't', body: payload });
    expect([201, 200]).toContain(create.status);
    const post_id = create.body.post.post_id;

    const html = await agent.get(`/courses/${course_id}/posts/${post_id}`);
    expect(html.status).toBe(200);
    // should not contain raw script tag
    expect(html.text).not.toContain(payload);
    // but should contain escaped markers
    expect(html.text).toMatch(/&lt;script&gt;|&lt;\/script&gt;/);
  });
});
