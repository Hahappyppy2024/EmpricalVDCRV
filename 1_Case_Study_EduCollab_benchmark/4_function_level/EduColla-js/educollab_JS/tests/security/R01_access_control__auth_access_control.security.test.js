const supertest = require('supertest');
const { makeAppWithFreshDb } = require('../_helpers');

function short(res) {
  return {
    status: res?.status,
    ct: res?.headers?.['content-type'],
    body: res?.body,
    text: (res?.text || '').slice(0, 200),
  };
}

async function loginOrDie(agent, username, password) {
  const attempts = [
    { path: '/api/login', body: { username, password } },
    { path: '/api/login', body: { email: username, password } },
    { path: '/api/auth/login', body: { username, password } },
    { path: '/api/auth/login', body: { email: username, password } },
    { path: '/api/session/login', body: { username, password } },
  ];

  let last = null;
  for (const a of attempts) {
    const r = await agent
      .post(a.path)
      .set('Content-Type', 'application/json')
      .send(a.body);

    last = r;

    if ([200, 201, 204, 302].includes(r.status)) {
      // probe: after login, /api/courses should not be unauthorized
      const probe = await agent.get('/api/courses');
      if (![401, 302].includes(probe.status)) return r;
    }
  }

  throw new Error(
    `LOGIN FAILED for user=${username}\n` +
      `Last response: ${JSON.stringify(short(last), null, 2)}\n` +
      `Probe /api/courses after login remained unauthorized.`
  );
}

async function registerOrDie(agent, username, password, displayName) {
  // Your backend explicitly requires display_name, but we try a few variants anyway.
  const attempts = [
    { path: '/api/register', body: { username, password, display_name: displayName } },
    { path: '/api/register', body: { username, password, displayName: displayName } },
    { path: '/api/register', body: { username, password, name: displayName } },

    { path: '/api/auth/register', body: { username, password, display_name: displayName } },
    { path: '/api/auth/register', body: { username, password, displayName: displayName } },
    { path: '/api/auth/register', body: { username, password, name: displayName } },

    { path: '/api/users', body: { username, password, display_name: displayName } }, // sometimes user creation endpoint
  ];

  let last = null;
  for (const a of attempts) {
    const r = await agent
      .post(a.path)
      .set('Content-Type', 'application/json')
      .send(a.body);

    last = r;

    if ([200, 201].includes(r.status)) {
      const b = r.body || {};
      const user_id =
        b?.user?.user_id ??
        b?.user_id ??
        b?.user?.id ??
        b?.id ??
        null;

      if (user_id) return { res: r, user_id };

      // Some APIs don't return id on register; we could still proceed by logging in and fetching "me".
      // But for now, fail with info to adapt.
      throw new Error(
        `REGISTER OK but cannot extract user_id.\n` +
          `Response: ${JSON.stringify(short(r), null, 2)}\n` +
          `Adjust extractor to match your API response shape.`
      );
    }
  }

  throw new Error(
    `REGISTER FAILED for username=${username}\n` +
      `Last response: ${JSON.stringify(short(last), null, 2)}`
  );
}

async function createCourseOrDie(agent, title, description) {
  const attempts = [
    { path: '/api/courses', body: { title, description } },
    { path: '/api/courses', body: { name: title, description } },
    { path: '/api/courses', body: { name: title, desc: description } },
    { path: '/api/course', body: { title, description } },
  ];

  let last = null;
  for (const a of attempts) {
    const r = await agent
      .post(a.path)
      .set('Content-Type', 'application/json')
      .send(a.body);

    last = r;

    if ([200, 201].includes(r.status)) {
      const b = r.body || {};
      const course_id =
        b?.course?.course_id ??
        b?.course?.id ??
        b?.course_id ??
        b?.id ??
        b?.courseId ??
        null;

      if (course_id) return { res: r, course_id };

      throw new Error(
        `CREATE COURSE OK but cannot extract course_id.\n` +
          `Response: ${JSON.stringify(short(r), null, 2)}`
      );
    }
  }

  throw new Error(
    `CREATE COURSE FAILED\n` +
      `Last response: ${JSON.stringify(short(last), null, 2)}`
  );
}

describe('Security: auth + access control smoke', () => {
  test('unauthenticated API requests are rejected', async () => {
    const { agent } = makeAppWithFreshDb('sec_auth_unauth.db');
    const res = await agent.get('/api/courses');
    expect([401, 302]).toContain(res.status);
  });

  test('student cannot create assignment (role enforcement)', async () => {
    const { app, agent } = makeAppWithFreshDb('sec_role.db');
    const request = supertest;

    // admin login
    await loginOrDie(agent, 'admin', 'admin123');

    // create course
    const { course_id } = await createCourseOrDie(agent, 'C', 'D');

    // register student (must use display_name)
    const stu = request.agent(app);
    const { user_id } = await registerOrDie(stu, 'alice', 'password', 'Alice');

    // add member as student
    const addMem = await agent
      .post(`/api/courses/${course_id}/members`)
      .set('Content-Type', 'application/json')
      .send({ user_id, role_in_course: 'student' });

    // allow 404 if endpoint not implemented, but should not be 500
    expect([200, 201, 204, 404, 400, 403]).toContain(addMem.status);

    // student login then try to create assignment -> should be rejected
    await loginOrDie(stu, 'alice', 'password');

    const asg = await stu
      .post(`/api/courses/${course_id}/assignments`)
      .set('Content-Type', 'application/json')
      .send({ title: 'A', description: 'D', due_at: new Date().toISOString() });

    expect([401, 403]).toContain(asg.status);
  });

  test('IDOR: user from other course cannot access course resources', async () => {
    const { app, agent } = makeAppWithFreshDb('sec_idor.db');
    const request = supertest;

    await loginOrDie(agent, 'admin', 'admin123');

    const { course_id: course1 } = await createCourseOrDie(agent, 'C1', 'D1');
    const { course_id: course2 } = await createCourseOrDie(agent, 'C2', 'D2');

    // register bob and add only to course2
    const bob = request.agent(app);
    const { user_id } = await registerOrDie(bob, 'bob', 'password', 'Bob');

    const addMem = await agent
      .post(`/api/courses/${course2}/members`)
      .set('Content-Type', 'application/json')
      .send({ user_id, role_in_course: 'student' });

    expect([200, 201, 204, 404, 400, 403]).toContain(addMem.status);

    await loginOrDie(bob, 'bob', 'password');

    const res = await bob.get(`/api/courses/${course1}/posts`);
    expect([401, 403]).toContain(res.status);
  });
});