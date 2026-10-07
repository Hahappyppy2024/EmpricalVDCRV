const { makeAppWithFreshDb, apiRegister, apiLogin, apiCreateCourse } = require('../_helpers');
const { addMemberByUsername } = require('./_pl_utils');



describe('Project-level: Auth', () => {
  test('register/login/me/logout', async () => {
    const { agent } = makeAppWithFreshDb('func_auth.db');

    const reg = await apiRegister(agent, { username: 'alice', password: 'password', display_name: 'Alice' });
    expect([200,201]).toContain(reg.status);

    const login = await apiLogin(agent, { username: 'alice', password: 'password' });
    expect(login.status).toBe(200);

    const me = await agent.get('/api/auth/me');
    expect(me.status).toBe(200);
    expect(me.body.user.username).toBe('alice');

    const lo = await agent.post('/api/auth/logout');
    expect([200,204]).toContain(lo.status);

    const me2 = await agent.get('/api/auth/me');
    expect([401,403]).toContain(me2.status);
  });
});
