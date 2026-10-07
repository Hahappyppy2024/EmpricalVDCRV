const { makeAppWithFreshDb, apiLogin, apiCreateCourse } = require('../_helpers');

describe('R05 Injection: CSV formula injection', () => {
  test('SEC-CSV1: Export members CSV mitigates formula injection', async () => {
    const { agent } = makeAppWithFreshDb('r05_sec_csv1.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    expect([200,201]).toContain(c.status);
    const course_id = c.body.course.course_id;

    const csv = Buffer.from("username,display_name,role_in_course\n=2+2,Evil,student\n");
    await agent.post(`/api/courses/${course_id}/members/import`)
      .attach('csv', csv, { filename: 'm.csv', contentType: 'text/csv' });

    const exp = await agent.get(`/api/courses/${course_id}/members/export.csv`);
    expect(exp.status).toBe(200);

    expect(exp.text).toMatch(/'=2\+2/);
  });
});
