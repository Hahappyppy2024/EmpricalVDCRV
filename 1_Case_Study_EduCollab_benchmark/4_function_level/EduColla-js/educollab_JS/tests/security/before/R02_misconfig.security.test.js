const { makeAppWithFreshDb } = require('../_helpers');

describe('Security: Misconfiguration', () => {
  test('does not expose X-Powered-By header', async () => {
    const { agent } = makeAppWithFreshDb('sec_misconfig_hdr.db');
    const res = await agent.get('/'); // any route
    expect(res.headers['x-powered-by']).toBeUndefined();
  });
});