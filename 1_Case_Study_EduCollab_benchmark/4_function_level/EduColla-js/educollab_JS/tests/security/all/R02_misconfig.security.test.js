const { makeAppWithFreshDb } = require('../_helpers');

console.log('SEC TEST VERSION: R02 misconfig headers v1');

describe('Security: Misconfiguration', () => {
  test('does not expose X-Powered-By header', async () => {
    const { agent } = makeAppWithFreshDb('sec_misconfig_hdr.db');

    // Use any route; allow 200/302/404 depending on app
    const res = await agent.get('/');

    expect([200, 302, 404]).toContain(res.status);
    // Header name is case-insensitive; supertest normalizes to lowercase keys
    expect(res.headers['x-powered-by']).toBeUndefined();
  });
});