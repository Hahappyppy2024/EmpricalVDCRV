
const { makeAppWithFreshDb, apiLogin, apiRegister, apiCreateCourse } = require('../_helpers');

describe('Security: auth + access control smoke', () => {
  test('unauthenticated API requests are rejected', async () => {
    const { agent } = makeAppWithFreshDb('sec_auth_unauth.db');
    const res = await agent.get('/api/courses');
    expect([401, 302]).toContain(res.status); // depending on middleware behavior
  });

  test('student cannot create assignment (role enforcement)', async () => {
    const { app, request, agent } = makeAppWithFreshDb('sec_role.db');
    // admin login and create course
    await apiLogin(agent, 'admin', 'admin123');
    const c = await apiCreateCourse(agent, 'C', 'D');
    const course_id = c.body.course.course_id;

    // register student and add as member with student role
    const stu = request.agent(app);
    await apiRegister(stu, 'alice', 'password', 'Alice');
    // add member via admin
    // user_id should be 2 in fresh db (admin seeded first)
    await agent.post(`/api/courses/${course_id}/members`).send({ user_id: 2, role_in_course: 'student' });

    // student login and try create assignment
    await apiLogin(stu, 'alice', 'password');
    const asg = await stu.post(`/api/courses/${course_id}/assignments`).send({
      title: 'A', description: 'D', due_at: new Date().toISOString()
    });
    expect([403, 401]).toContain(asg.status);
  });

  test('IDOR: user from other course cannot access course resources', async () => {
    const { app, request, agent } = makeAppWithFreshDb('sec_idor.db');
    await apiLogin(agent, 'admin', 'admin123');

    const c1 = await apiCreateCourse(agent, 'C1', 'D1');
    const course1 = c1.body.course.course_id;

    // create second course
    const c2 = await apiCreateCourse(agent, 'C2', 'D2');
    const course2 = c2.body.course.course_id;

    // register bob, add to course2 only
    const bob = request.agent(app);
    await apiRegister(bob, 'bob', 'password', 'Bob');
    await agent.post(`/api/courses/${course2}/members`).send({ user_id: 2, role_in_course: 'student' });

    await apiLogin(bob, 'bob', 'password');

    // bob tries to list posts in course1 where he is not a member
    const res = await bob.get(`/api/courses/${course1}/posts`);
    expect([403, 401]).toContain(res.status);
  });
});
