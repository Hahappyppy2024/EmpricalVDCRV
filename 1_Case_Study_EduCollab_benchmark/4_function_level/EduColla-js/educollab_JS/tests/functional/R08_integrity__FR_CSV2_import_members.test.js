const { makeAppWithFreshDb, apiRegister, apiLogin, apiCreateCourse } = require('../_helpers');
const { zipBuffer, getUserIdByUsername } = require('./_req_utils');

describe('Requirement-level FR-CSV2', () => {
  test('import members csv', async () => {
    const { agent } = makeAppWithFreshDb('csv2.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C3', description: 'D3' });
    expect(c.status).toBe(201);
    const course_id = c.body.course.course_id;

    const csv = Buffer.from("username,display_name,role_in_course\nbob,Bob Student,student\n");
    const imp = await agent
      .post(`/api/courses/${course_id}/members/import`)
      .attach('csv', csv, { filename: 'members.csv', contentType: 'text/csv' });

    expect(imp.status).toBe(200);
    expect(imp.body.addedMemberships).toBeGreaterThanOrEqual(1);

    const list = await agent.get(`/api/courses/${course_id}/members`);
    expect(list.status).toBe(200);
    const usernames = list.body.members.map(m => m.username);
    expect(usernames).toContain('bob');
  });
});
