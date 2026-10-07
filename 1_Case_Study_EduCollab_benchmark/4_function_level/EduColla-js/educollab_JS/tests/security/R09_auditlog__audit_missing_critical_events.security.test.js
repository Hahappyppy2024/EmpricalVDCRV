const { makeAppWithFreshDb, apiLogin } = require('../_helpers');

describe('R09 Security: Logging for critical events', () => {
  test('failed login SHOULD be recorded in audit log (fails if missing)', async () => {
    const { agent } = makeAppWithFreshDb('sec_a09_failed_login_required.db');

    // trigger a failed login
    const bad = await agent.post('/api/auth/login')
      .send({ username: 'admin', password: 'wrong-password' });
    expect([400, 401, 403]).toContain(bad.status);

    // login successfully and fetch audit log
    const ok = await apiLogin(agent, { username: 'admin', password: 'admin123' });
    expect([200, 302]).toContain(ok.status);

    // Prefer JSON audit API. If missing, treat as FAIL for this dataset-style test.
    const r = await agent.get('/api/admin/audit');

    expect(r.status).toBe(200);
    expect(r.body && Array.isArray(r.body.audit)).toBe(true);

    const rows = r.body.audit;

    const hasFailedLogin = rows.some((row) => {
      const a = String(row.action || '').toLowerCase();
      return a.includes('login_failed') || a.includes('auth_failed') || a.includes('failed_login');
    });

    // PASS => logging exists; FAIL => missing => vulnerability exists
    expect(hasFailedLogin).toBe(true);
  });
});