const { apiLogin } = require('../_helpers');

test('session cookie includes HttpOnly and SameSite', async () => {
  const { agent } = makeAppWithFreshDb('sec_misconfig_cookie.db');
  await apiLogin(agent, 'admin', 'admin123');

  const setCookie = (agent.jar && agent.jar.getCookies) ? null : null; // ignore, supertest gives headers
  // hit any endpoint to get/set cookie
  const res = await agent.get('/api/me'); // or any authenticated endpoint you already have
  const cookies = res.headers['set-cookie'] || [];
  const cookieStr = cookies.join(' ');
  expect(cookieStr).toMatch(/HttpOnly/i);
  expect(cookieStr).toMatch(/SameSite=/i);
});