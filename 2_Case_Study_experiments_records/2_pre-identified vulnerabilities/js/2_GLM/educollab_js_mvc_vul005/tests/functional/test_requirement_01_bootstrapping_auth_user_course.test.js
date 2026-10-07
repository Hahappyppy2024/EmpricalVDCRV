const { startFreshServer, stopServer, openDb, ApiClient } = require('./api_helpers');

jest.setTimeout(30000);

function ok(res) {
  expect([200, 201, 302]).toContain(res.status);
}

describe('Requirement 01: Bootstrapping + Auth + User + Course', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  });

  afterAll(async () => {
    await stopServer(server);
  });

  test('seed admin, register/login/logout user, list users, and course CRUD all work', async () => {
    const admin = new ApiClient(server.baseUrl);
    const user = new ApiClient(server.baseUrl);
    const db = openDb();

    try {
      // FR-A2 seed_admin
      const seededAdmin = db
        .prepare('SELECT user_id, username FROM users WHERE username=?')
        .get('admin');
      expect(seededAdmin && seededAdmin.user_id).toBeTruthy();

      // FR-U1 register_student
      const reg = await user.post('/api/auth/register', {
        json: {
          username: 'req1_user',
          password: 'pass123',
          display_name: 'Req1 User',
        },
      });
      ok(reg);

      const regUser = db
        .prepare('SELECT user_id, username, display_name FROM users WHERE username=?')
        .get('req1_user');
      expect(regUser && regUser.user_id).toBeTruthy();
      expect(regUser.display_name).toBe('Req1 User');

      // FR-U4 me
      const meRes = await user.get('/api/auth/me');
      ok(meRes);

      // FR-U2 login
      const adminLogin = await admin.post('/api/auth/login', {
        json: { username: 'admin', password: 'admin123' },
      });
      ok(adminLogin);

      // FR-U5 list_users
      const usersRes = await admin.get('/api/users');
      ok(usersRes);

      // FR-C1 create_course
      const createCourse = await admin.post('/api/courses', {
        json: {
          title: 'Req1 Course',
          description: 'Requirement 01 course',
        },
      });
      ok(createCourse);

      const courseRow = db
        .prepare('SELECT course_id, title, description FROM courses WHERE title=? ORDER BY course_id DESC')
        .get('Req1 Course');
      expect(courseRow && courseRow.course_id).toBeTruthy();
      expect(courseRow.description).toBe('Requirement 01 course');
      const course_id = courseRow.course_id;

      // FR-C2 list_courses
      ok(await admin.get('/api/courses'));

      // FR-C3 get_course
      ok(await admin.get(`/api/courses/${course_id}`));

      // FR-C4 update_course
      const updateCourse = await admin.put(`/api/courses/${course_id}`, {
        json: {
          title: 'Req1 Course Updated',
          description: 'Requirement 01 course updated',
        },
      });
      ok(updateCourse);

      const updatedCourse = db
        .prepare('SELECT title, description FROM courses WHERE course_id=?')
        .get(course_id);
      expect(updatedCourse.title).toBe('Req1 Course Updated');
      expect(updatedCourse.description).toBe('Requirement 01 course updated');

      // FR-C5 delete_or_archive_course
      const deleteCourse = await admin.delete(`/api/courses/${course_id}`);
      ok(deleteCourse);

      const deletedCourse = db
        .prepare('SELECT course_id FROM courses WHERE course_id=?')
        .get(course_id);
      expect(deletedCourse).toBeUndefined();

      // FR-U3 logout
      const logout = await user.post('/api/auth/logout', { json: {} });
      ok(logout);

      const meAfterLogout = await fetch(`${server.baseUrl}/api/auth/me`, {
        headers: { cookie: user.cookie || '' },
        redirect: 'manual',
      });
      expect([401, 302]).toContain(meAfterLogout.status);
    } finally {
      try { db.close(); } catch (_) {}
    }
  });
});