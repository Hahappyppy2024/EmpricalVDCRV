const test = require('node:test');
const assert = require('node:assert/strict');
const { startFreshServer, stopServer, ApiClient } = require('./_helper');

test('API functional: membership add/list/update/remove and my-memberships flow', async () => {
  const server = await startFreshServer();
  try {
    const teacher = new ApiClient(server.baseUrl);
    const student = new ApiClient(server.baseUrl);

    const teacherName = `teacher_${Date.now()}`;
    const studentName = `student_${Date.now()}`;

    let res = await teacher.post('/api/auth/register', {
      json: { username: teacherName, password: 'pass123', display_name: 'Teacher User' }
    });
    assert.equal(res.status, 201);
    assert.equal(res.data.user.username, teacherName);
    const teacherId = res.data.user.user_id;
    assert.ok(teacherId);

    res = await teacher.post('/api/courses', {
      json: { title: 'Membership Course', description: 'for API tests' }
    });
    assert.equal(res.status, 201);
    const courseId = res.data.course.course_id;
    assert.ok(courseId);

    res = await student.post('/api/auth/register', {
      json: { username: studentName, password: 'pass123', display_name: 'Student User' }
    });
    assert.equal(res.status, 201);
    const studentId = res.data.user.user_id;
    assert.ok(studentId);

    res = await teacher.post(`/api/courses/${courseId}/members`, {
      json: { user_id: studentId, role_in_course: 'student' }
    });
    assert.equal(res.status, 201);
    assert.equal(res.data.membership.course_id, courseId);
    assert.equal(res.data.membership.user_id, studentId);
    assert.equal(res.data.membership.role_in_course, 'student');
    const membershipId = res.data.membership.membership_id;
    assert.ok(membershipId);

    res = await teacher.get(`/api/courses/${courseId}/members`);
    assert.equal(res.status, 200);
    assert.ok(Array.isArray(res.data.memberships));
    assert.ok(
      res.data.memberships.some(
        (m) => m.user_id === studentId && m.role_in_course === 'student'
      )
    );
    assert.ok(
      res.data.memberships.some(
        (m) => m.user_id === teacherId && m.username === teacherName && m.role_in_course === 'teacher'
      )
    );

    res = await teacher.put(`/api/courses/${courseId}/members/${membershipId}`, {
      json: { role_in_course: 'assistant' }
    });
    assert.equal(res.status, 200);
    assert.equal(res.data.membership.role_in_course, 'assistant');

    res = await student.get('/api/memberships');
    assert.equal(res.status, 200);
    assert.ok(
      res.data.memberships.some(
        (m) => m.course_id === courseId && m.role_in_course === 'assistant'
      )
    );

    res = await teacher.delete(`/api/courses/${courseId}/members/${membershipId}`);
    assert.equal(res.status, 200);
    assert.equal(res.data.success, true);

    res = await teacher.get(`/api/courses/${courseId}/members`);
    assert.equal(res.status, 200);
    assert.ok(!res.data.memberships.some((m) => m.membership_id === membershipId));

    res = await student.get('/api/memberships');
    assert.equal(res.status, 200);
    assert.ok(!res.data.memberships.some((m) => m.course_id === courseId));
  } finally {
    await stopServer(server);
  }
});

test('API functional: non-teacher course member cannot add a membership', async () => {
  const server = await startFreshServer();
  try {
    const teacher = new ApiClient(server.baseUrl);
    const student = new ApiClient(server.baseUrl);
    const outsider = new ApiClient(server.baseUrl);

    const teacherName = `teacher_perm_${Date.now()}`;
    const studentName = `student_perm_${Date.now()}`;
    const outsiderName = `outsider_perm_${Date.now()}`;

    let res = await teacher.post('/api/auth/register', {
      json: { username: teacherName, password: 'pass123', display_name: 'Teacher Perm' }
    });
    assert.equal(res.status, 201);

    res = await teacher.post('/api/courses', {
      json: { title: 'Permission Course', description: '' }
    });
    assert.equal(res.status, 201);
    const courseId = res.data.course.course_id;
    assert.ok(courseId);

    res = await student.post('/api/auth/register', {
      json: { username: studentName, password: 'pass123', display_name: 'Student Perm' }
    });
    assert.equal(res.status, 201);
    const studentId = res.data.user.user_id;
    assert.ok(studentId);

    res = await outsider.post('/api/auth/register', {
      json: { username: outsiderName, password: 'pass123', display_name: 'Outsider Perm' }
    });
    assert.equal(res.status, 201);
    const outsiderId = res.data.user.user_id;
    assert.ok(outsiderId);

    res = await teacher.post(`/api/courses/${courseId}/members`, {
      json: { user_id: studentId, role_in_course: 'student' }
    });
    assert.equal(res.status, 201);

    res = await student.post(`/api/courses/${courseId}/members`, {
      json: { user_id: outsiderId, role_in_course: 'student' }
    });
    assert.equal(res.status, 403);
    assert.equal(res.data.success, false);
  } finally {
    await stopServer(server);
  }
});