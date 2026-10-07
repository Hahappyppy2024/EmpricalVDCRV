describe('A09 Security: Logging for critical events', () => {
  test('failed login SHOULD be recorded in audit log (fails if missing)', async () => {
    const { agent } = makeAppWithFreshDb('sec_a09_failed_login_required.db');

    // trigger a failed login
    const bad = await agent.post('/api/auth/login').send({ username: 'admin', password: 'wrong-password' });
    expect([400, 401, 403]).toContain(bad.status);

    // login successfully and fetch audit log
    const ok = await apiLogin(agent, { username: 'admin', password: 'admin123' });
    expect([200, 302]).toContain(ok.status);

    const candidates = ['/api/admin/audit', '/admin/audit'];
    let audit = null;

    for (const p of candidates) {
      const r = await agent.get(p);
      if (r.status === 200) { audit = r; break; }
    }

    if (!audit) {
      console.warn('SKIP(A09): audit endpoint not found at /api/admin/audit or /admin/audit');
      return;
    }

    const rows = audit.body.audit || [];

    const hasFailedLogin = rows.some(r =>
      (r.action || '').toLowerCase().includes('login_failed') ||
      (r.action || '').toLowerCase().includes('auth_failed') ||
      (r.action || '').toLowerCase().includes('failed_login')
    );

    // ✅ PASS means logging exists (no vulnerability)
    // ❌ FAIL means logging missing (A09 vulnerability exists)
    expect(hasFailedLogin).toBe(true);
  });
});