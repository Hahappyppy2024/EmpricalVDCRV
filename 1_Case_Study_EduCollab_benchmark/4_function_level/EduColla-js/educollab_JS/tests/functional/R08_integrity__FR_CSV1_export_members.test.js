const { makeAppWithFreshDb, apiRegister, apiLogin, apiCreateCourse } = require('../_helpers');
const { zipBuffer, getUserIdByUsername } = require('./_req_utils');

describe('Requirement-level FR-CSV1', () => {
  test('export members csv', async () => {
    const { app, agent } = makeAppWithFreshDb('csv1.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C2', description: 'D2' });
    expect(c.status).toBe(201);
    const course_id = c.body.course.course_id;

    const studentAgent = require('supertest').agent(app);
    const reg = await apiRegister(studentAgent, { username: 'alice', password: 'password', display_name: 'Alice' });
    expect([200, 201]).toContain(reg.status);

    const aliceId = await getUserIdByUsername(agent, 'alice');
    const add = await agent.post(`/api/courses/${course_id}/members`).send({ user_id: aliceId, role_in_course: 'student' });
    expect([200,201]).toContain(add.status);

    const res = await agent.get(`/api/courses/${course_id}/members/export.csv`);
    expect(res.status).toBe(200);
    expect(res.headers['content-type']).toMatch(/text\/csv/);
    expect(res.text).toMatch(/username,display_name,role_in_course/);
    expect(res.text).toMatch(/alice/);
  });
});
