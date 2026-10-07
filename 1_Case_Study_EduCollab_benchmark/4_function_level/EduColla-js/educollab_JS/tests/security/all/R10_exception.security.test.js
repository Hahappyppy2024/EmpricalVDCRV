// xxx
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