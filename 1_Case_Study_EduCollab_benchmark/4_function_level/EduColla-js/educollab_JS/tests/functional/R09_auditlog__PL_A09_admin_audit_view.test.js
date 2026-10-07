const { makeAppWithFreshDb, apiLogin } = require('../_helpers');

describe('A09 Functional: Admin audit log view', () => {
  test('admin can view /admin/audit and /api/admin/audit returns rows', async () => {
    const { agent } = makeAppWithFreshDb('func_a09_audit.db');

    // login as admin
    const lg = await apiLogin(agent, { username: 'admin', password: 'admin123' });
    expect([200, 302]).toContain(lg.status);

    // web page
    const page = await agent.get('/admin/audit');
    expect(page.status).toBe(200);
    expect(page.text).toMatch(/Audit Log/i);

    // api
    const api = await agent.get('/api/admin/audit');
    expect(api.status).toBe(200);
    expect(api.body).toHaveProperty('audit');
    expect(Array.isArray(api.body.audit)).toBe(true);

    // viewing should create an audit_view entry
    const hasView = api.body.audit.some(r => r.action === 'audit_view');
    expect(hasView).toBe(true);
  });
});
