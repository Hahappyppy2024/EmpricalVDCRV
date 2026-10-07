const test = require('node:test');
const {
  assert,
  startFreshServer,
  stopServer,
  ApiClient,
  registerAndLogin
} = require('./_helper');

let server;

test.before(async () => {
  server = await startFreshServer();
});

test.after(async () => {
  await stopServer(server);
});

test('API functional flow: register/login/me + admin users + course CRUD', async () => {
  const alice = new ApiClient(server.baseUrl);
  const admin = new ApiClient(server.baseUrl);

  const createdUser = await registerAndLogin(alice, 'alice', 'Alice');
  assert.equal(createdUser.username, 'alice');
  assert.equal(createdUser.display_name, 'Alice');

  const meRes = await alice.get('/api/auth/me');
  assert.equal(meRes.status, 200);
  assert.equal(meRes.data.success, true);
  assert.equal(meRes.data.user.username, 'alice');

  const createMissingTitle = await alice.post('/api/courses', { description: 'missing title' });
  assert.equal(createMissingTitle.status, 400);
  assert.match(createMissingTitle.data.error, /title is required/i);

  const createCourse = await alice.post('/api/courses', {
    title: 'Distributed Systems',
    description: 'Spring offering'
  });
  assert.equal(createCourse.status, 201);
  assert.equal(createCourse.data.success, true);
  assert.equal(createCourse.data.course.title, 'Distributed Systems');
  assert.equal(createCourse.data.course.created_by, createdUser.user_id);
  const courseId = createCourse.data.course.course_id;

  const listCourses = await alice.get('/api/courses');
  assert.equal(listCourses.status, 200);
  assert.equal(listCourses.data.success, true);
  assert.ok(Array.isArray(listCourses.data.courses));
  assert.ok(listCourses.data.courses.some(c => c.course_id === courseId && c.title === 'Distributed Systems'));

  const getCourse = await alice.get(`/api/courses/${courseId}`);
  assert.equal(getCourse.status, 200);
  assert.equal(getCourse.data.course.creator_username, 'alice');

  const updateCourse = await alice.put(`/api/courses/${courseId}`, {
    title: 'Distributed Systems - Updated',
    description: 'Updated description'
  });
  assert.equal(updateCourse.status, 200);
  assert.equal(updateCourse.data.course.title, 'Distributed Systems - Updated');
  assert.equal(updateCourse.data.course.description, 'Updated description');

  const notFoundCourse = await alice.get('/api/courses/999999');
  assert.equal(notFoundCourse.status, 404);

  const logoutRes = await alice.post('/api/auth/logout');
  assert.equal(logoutRes.status, 200);
  assert.equal(logoutRes.data.success, true);

  const meAfterLogout = await alice.get('/api/auth/me');
  assert.equal(meAfterLogout.status, 401);

  const adminLogin = await admin.post('/api/auth/login', {
    username: 'admin',
    password: 'adminpass'
  });
  assert.equal(adminLogin.status, 200);
  assert.equal(adminLogin.data.user.username, 'admin');

  const usersAsAdmin = await admin.get('/api/users');
  assert.equal(usersAsAdmin.status, 200);
  assert.equal(usersAsAdmin.data.success, true);
  assert.ok(usersAsAdmin.data.users.some(u => u.username === 'admin'));
  assert.ok(usersAsAdmin.data.users.some(u => u.username === 'alice'));

  const nonAdmin = new ApiClient(server.baseUrl);
  await registerAndLogin(nonAdmin, 'bob', 'Bob');
  const usersAsBob = await nonAdmin.get('/api/users');
  assert.equal(usersAsBob.status, 403);
  assert.match(usersAsBob.data.error, /admin/i);

  const deleteCourse = await admin.delete(`/api/courses/${courseId}`);
  assert.equal(deleteCourse.status, 200);
  assert.equal(deleteCourse.data.success, true);

  const fetchDeleted = await admin.get(`/api/courses/${courseId}`);
  assert.equal(fetchDeleted.status, 404);
});
