const { makeAppWithFreshDb } = require('../_helpers');

describe('Security: Stacktrace leakage (sanity)', () => {
  test('error responses do not leak stack traces / file paths', async () => {
    const { agent } = makeAppWithFreshDb('sec_stacktrace.db');

    // Try to trigger a parsing/runtime-style error:
    // malformed JSON body; even if app returns 400/415/500, it must not leak stack/paths.
    const res = await agent
      .post('/api/courses')
      .set('Content-Type', 'application/json')
      .send('{"title":'); // malformed JSON

    // Accept common statuses across implementations.
    expect([400, 415, 500]).toContain(res.status);

    // Normalize body to string for scanning
    const bodyStr =
      typeof res.body === 'object' && res.body !== null
        ? JSON.stringify(res.body)
        : (res.text || '');

    // Common stack/path leak signatures across Node/Express
    expect(bodyStr).not.toMatch(/ReferenceError|TypeError|SyntaxError/i);
    expect(bodyStr).not.toMatch(/\bat\s.+\(.+:\d+:\d+\)/i); // stack frame: "at fn (file:line:col)"
    expect(bodyStr).not.toMatch(/node_modules|internal\/|bootstrap_node|webpack/i);

    // Common absolute/relative path patterns (Windows + Unix)
    expect(bodyStr).not.toMatch(/[A-Z]:\\|\\\\/i);   // Windows path like C:\...
    expect(bodyStr).not.toMatch(/\/(usr|home|mnt|var)\//i); // Unix-ish absolute paths
  });
});