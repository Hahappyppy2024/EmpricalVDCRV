const { makeAppWithFreshDb } = require('../_helpers');

console.log('SEC TEST VERSION: R02 session cookie flags v2 (self-login)');

function short(res) {
  return {
    status: res?.status,
    ct: res?.headers?.['content-type'],
    body: res?.body,
    text: (res?.text || '').slice(0, 200),
  };
}

function cookieHeaderToString(setCookieHeaders) {
  const arr = Array.isArray(setCookieHeaders)
    ? setCookieHeaders
    : setCookieHeaders
    ? [setCookieHeaders]
    : [];
  return arr.join(' ');
}

async function loginOrDie(agent, username, password) {
  const attempts = [
    { path: '/api/login', body: { username, password } },
    { path: '/api/login', body: { email: username, password } },
    { path: '/api/auth/login', body: { username, password } },
    { path: '/api/auth/login', body: { email: username, password } },
  ];

  let last = null;
  for (const a of attempts) {
    const r = await agent
      .post(a.path)
      .set('Content-Type', 'application/json')
      .send(a.body);
    last = r;

    if ([200, 201, 204, 302].includes(r.status)) {
      // probe: after login, a protected endpoint should not be 401/302
      const probe = await agent.get('/api/courses');
      if (![401, 302].includes(probe.status)) return r;
    }
  }

  throw new Error(
    `LOGIN FAILED for user=${username}\n` +
      `Last response: ${JSON.stringify(short(last), null, 2)}\n` +
      `Tip: your login endpoint/payload keys may differ.`
  );
}

describe('Security: Misconfiguration (Session Cookie Flags)', () => {
  test('session cookie includes HttpOnly and SameSite', async () => {
    const { agent } = makeAppWithFreshDb('sec_misconfig_cookie.db');

    // 1) login (do not rely on helper, since helper returned 400 earlier)
    const loginRes = await loginOrDie(agent, 'admin', 'admin123');

    // 2) Prefer cookie set during login
    let cookieStr = cookieHeaderToString(loginRes?.headers?.['set-cookie']);

    // 3) Fallback: some apps only set cookie on first authenticated request
    if (!cookieStr) {
      const probe1 = await agent.get('/api/me');
      cookieStr = cookieHeaderToString(probe1?.headers?.['set-cookie']);
    }
    if (!cookieStr) {
      const probe2 = await agent.get('/api/courses');
      cookieStr = cookieHeaderToString(probe2?.headers?.['set-cookie']);
    }

    if (!cookieStr) {
      throw new Error(
        'No Set-Cookie header observed after successful login + probes.\n' +
          'This may mean the app uses token-based auth (no session cookie),\n' +
          'or cookies are not being set in this test environment.\n' +
          `loginRes=${JSON.stringify(short(loginRes), null, 2)}\n`
      );
    }

    // Real checks (keep as "real fail" if missing)
    expect(cookieStr).toMatch(/httponly/i);
    expect(cookieStr).toMatch(/samesite=/i);

    // Optional stronger checks:
    // expect(cookieStr).toMatch(/samesite=(lax|strict|none)/i);
    // if (/samesite=none/i.test(cookieStr)) expect(cookieStr).toMatch(/secure/i);
  });
});