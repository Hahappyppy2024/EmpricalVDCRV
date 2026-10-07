const { startFreshServer, stopServer, openDb, ApiClient } = require('./api_helpers');

jest.setTimeout(30000);

function ok(res) {
  expect([200, 201, 302]).toContain(res.status);
}

function getUserId(db, username) {
  const row = db.prepare('SELECT user_id FROM users WHERE username=?').get(username);
  return row && row.user_id;
}

describe('Requirement 02: Membership + Role Enforcement', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  });

  afterAll(async () => {
    await stopServer(server);
  });

  test('admin/teacher can manage memberships and logged-in users can view member lists and own memberships', async () => {
    const admin = new ApiClient(server.baseUrl);
    const teacher = new ApiClient(server.baseUrl);
    const student = new ApiClient(server.baseUrl);
    const db = openDb();

    try {
      ok(await admin.post('/api/auth/login', {
        json: { username: 'admin', password: 'admin123' },
      }));

      ok(await teacher.post('/api/auth/register', {
        json: {
          username: 'req2_teacher',
          password: 'pass123',
          display_name: 'Req2 Teacher',
        },
      }));

      ok(await student.post('/api/auth/register', {
        json: {
          username: 'req2_student',
          password: 'pass123',
          display_name: 'Req2 Student',
        },
      }));

      const teacherId = getUserId(db, 'req2_teacher');
      const studentId = getUserId(db, 'req2_student');
      expect(teacherId).toBeTruthy();
      expect(studentId).toBeTruthy();

      ok(await admin.post('/api/courses', {
        json: {
          title: 'Req2 Course',
          description: 'Membership requirement course',
        },
      }));

      const courseRow = db
        .prepare('SELECT course_id FROM courses WHERE title=? ORDER BY course_id DESC')
        .get('Req2 Course');
      expect(courseRow && courseRow.course_id).toBeTruthy();
      const course_id = courseRow.course_id;

      // FR-M1 add_member: admin adds teacher
      const addTeacher = await admin.post(`/api/courses/${course_id}/members`, {
        json: {
          user_id: teacherId,
          role_in_course: 'teacher',
        },
      });
      ok(addTeacher);

      const teacherMembership = db
        .prepare('SELECT membership_id, role_in_course FROM memberships WHERE course_id=? AND user_id=?')
        .get(course_id, teacherId);
      expect(teacherMembership && teacherMembership.membership_id).toBeTruthy();
      expect(teacherMembership.role_in_course).toBe('teacher');

      // FR-M2 list_members: any logged-in user can view
      ok(await teacher.get(`/api/courses/${course_id}/members`));

      // FR-M1 add_member again: teacher adds student
      const addStudent = await teacher.post(`/api/courses/${course_id}/members`, {
        json: {
          user_id: studentId,
          role_in_course: 'student',
        },
      });
      ok(addStudent);

      const studentMembership = db
        .prepare('SELECT membership_id, role_in_course FROM memberships WHERE course_id=? AND user_id=?')
        .get(course_id, studentId);
      expect(studentMembership && studentMembership.membership_id).toBeTruthy();
      expect(studentMembership.role_in_course).toBe('student');
      const student_membership_id = studentMembership.membership_id;

      // FR-M2 list_members: student can also view member list
      ok(await student.get(`/api/courses/${course_id}/members`));

      // FR-M3 update_member_role: teacher updates student role
      const updateRole = await teacher.put(
        `/api/courses/${course_id}/members/${student_membership_id}`,
        { json: { role_in_course: 'assistant' } }
      );
      ok(updateRole);

      const updatedRole = db
        .prepare('SELECT role_in_course FROM memberships WHERE membership_id=?')
        .get(student_membership_id);
      expect(updatedRole.role_in_course).toBe('assistant');

      // FR-M5 my_memberships
      ok(await student.get('/api/memberships'));

      // FR-M4 remove_member
      const removeMember = await teacher.delete(
        `/api/courses/${course_id}/members/${student_membership_id}`
      );
      ok(removeMember);

      const removed = db
        .prepare('SELECT membership_id FROM memberships WHERE membership_id=?')
        .get(student_membership_id);
      expect(removed).toBeUndefined();
    } finally {
      try { db.close(); } catch (_) {}
    }
  });
});