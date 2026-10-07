const { makeAppWithFreshDb, apiLogin } = require('../_helpers');

describe('A09 Security: Missing logging for critical events', () => {
  test('failed login is NOT recorded in audit log (A09 vulnerability)', async () => {
    const { agent } = makeAppWithFreshDb('sec_a09_missing_failed_login.db');

    // trigger a failed login (critical event) - should be 401/403
    const bad = await agent.post('/api/auth/login').send({ username: 'admin', password: 'wrong-password' });
    expect([400, 401, 403]).toContain(bad.status);

    // now login successfully as admin and fetch audit log
    const ok = await apiLogin(agent, { username: 'admin', password: 'admin123' });
    expect([200, 302]).toContain(ok.status);

    const audit = await agent.get('/api/admin/audit');
    expect(audit.status).toBe(200);
    const rows = audit.body.audit || [];

    // A09: critical event "failed login" should have been logged, but is intentionally missing.
    const hasFailedLogin = rows.some(r =>
      (r.action || '').toLowerCase().includes('login_failed') ||
      (r.action || '').toLowerCase().includes('auth_failed') ||
      (r.action || '').toLowerCase().includes('failed_login')
    );
    expect(hasFailedLogin).toBe(false);
  });
});
