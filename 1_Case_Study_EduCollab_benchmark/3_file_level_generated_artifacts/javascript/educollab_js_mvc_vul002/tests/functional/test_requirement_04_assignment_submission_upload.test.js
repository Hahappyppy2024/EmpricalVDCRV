const { startFreshServer, stopServer, openDb, ApiClient } = require('./api_helpers');

jest.setTimeout(30000);

function ok(res) {
  expect([200, 201, 302]).toContain(res.status);
}

function getUserId(db, username) {
  const row = db.prepare('SELECT user_id FROM users WHERE username=?').get(username);
  return row && row.user_id;
}

describe('Requirement 04: Assignment + Submission + Upload', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  });

  afterAll(async () => {
    await stopServer(server);
  });

  test('staff can manage assignments/uploads and student can create/update/view own submissions', async () => {
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
          username: 'req4_teacher',
          password: 'pass123',
          display_name: 'Req4 Teacher',
        },
      }));

      ok(await student.post('/api/auth/register', {
        json: {
          username: 'req4_student',
          password: 'pass123',
          display_name: 'Req4 Student',
        },
      }));

      const teacherId = getUserId(db, 'req4_teacher');
      const studentId = getUserId(db, 'req4_student');
      expect(teacherId).toBeTruthy();
      expect(studentId).toBeTruthy();

      ok(await admin.post('/api/courses', {
        json: {
          title: 'Req4 Course',
          description: 'Assignment submission upload course',
        },
      }));

      const courseRow = db
        .prepare('SELECT course_id FROM courses WHERE title=? ORDER BY course_id DESC')
        .get('Req4 Course');
      expect(courseRow && courseRow.course_id).toBeTruthy();
      const course_id = courseRow.course_id;

      ok(await admin.post(`/api/courses/${course_id}/members`, {
        json: { user_id: teacherId, role_in_course: 'teacher' },
      }));
      ok(await admin.post(`/api/courses/${course_id}/members`, {
        json: { user_id: studentId, role_in_course: 'student' },
      }));

      // FR-AS1 create_assignment
      const due1 = new Date(Date.now() + 3600 * 1000).toISOString();
      const createAssignment = await teacher.post(`/api/courses/${course_id}/assignments`, {
        json: {
          title: 'Req4 Assignment',
          description: 'Initial assignment',
          due_at: due1,
        },
      });
      ok(createAssignment);

      const asgRow = db
        .prepare('SELECT assignment_id, title FROM assignments WHERE course_id=? ORDER BY assignment_id DESC')
        .get(course_id);
      expect(asgRow && asgRow.assignment_id).toBeTruthy();
      const assignment_id = asgRow.assignment_id;

      // FR-AS2 list_assignments
      ok(await student.get(`/api/courses/${course_id}/assignments`));

      // FR-AS3 get_assignment
      ok(await student.get(`/api/courses/${course_id}/assignments/${assignment_id}`));

      // FR-AS4 update_assignment
      const due2 = new Date(Date.now() + 7200 * 1000).toISOString();
      const updateAssignment = await teacher.put(
        `/api/courses/${course_id}/assignments/${assignment_id}`,
        {
          json: {
            title: 'Req4 Assignment Updated',
            description: 'Updated assignment',
            due_at: due2,
          },
        }
      );
      ok(updateAssignment);

      const updatedAssignment = db
        .prepare('SELECT title, description, due_at FROM assignments WHERE assignment_id=?')
        .get(assignment_id);
      expect(updatedAssignment.title).toBe('Req4 Assignment Updated');
      expect(updatedAssignment.description).toBe('Updated assignment');
      expect(updatedAssignment.due_at).toBe(due2);

      // FR-UP1 upload_file
      const form = new FormData();
      form.append('file', new Blob([Buffer.from('req4 file content')], { type: 'text/plain' }), 'req4.txt');

      const uploadRes = await teacher.post(`/api/courses/${course_id}/uploads`, {
        body: form,
      });
      ok(uploadRes);

      const uploadRow = db
        .prepare('SELECT upload_id, original_name, storage_path FROM uploads WHERE course_id=? ORDER BY upload_id DESC')
        .get(course_id);
      expect(uploadRow && uploadRow.upload_id).toBeTruthy();
      expect(uploadRow.original_name).toBe('req4.txt');
      const upload_id = uploadRow.upload_id;

      // FR-UP2 list_uploads
      ok(await student.get(`/api/courses/${course_id}/uploads`));

      // FR-UP3 download_upload
      const dlRes = await fetch(
        `${server.baseUrl}/api/courses/${course_id}/uploads/${upload_id}/download`,
        { headers: { cookie: student.cookie }, redirect: 'manual' }
      );
      expect([200, 302]).toContain(dlRes.status);

      if (dlRes.status === 200) {
        const text = await dlRes.text();
        expect(text).toBe('req4 file content');
      }

      // FR-SB1 create_submission
      const createSubmission = await student.post(
        `/api/courses/${course_id}/assignments/${assignment_id}/submissions`,
        {
          json: {
            content_text: 'req4 submission text',
            attachment_upload_id: null,
          },
        }
      );
      ok(createSubmission);

      const submissionRow = db
        .prepare('SELECT submission_id, content_text FROM submissions WHERE course_id=? AND assignment_id=? AND student_id=?')
        .get(course_id, assignment_id, studentId);
      expect(submissionRow && submissionRow.submission_id).toBeTruthy();
      expect(submissionRow.content_text).toBe('req4 submission text');
      const submission_id = submissionRow.submission_id;

      // FR-SB2 update_my_submission
      const updateSubmission = await student.put(
        `/api/courses/${course_id}/assignments/${assignment_id}/submissions/${submission_id}`,
        {
          json: {
            content_text: 'req4 submission updated',
            attachment_upload_id: null,
          },
        }
      );
      ok(updateSubmission);

      const updatedSubmission = db
        .prepare('SELECT content_text FROM submissions WHERE submission_id=?')
        .get(submission_id);
      expect(updatedSubmission.content_text).toBe('req4 submission updated');

      // FR-SB3 list_my_submissions
      ok(await student.get('/api/my/submissions'));

      // FR-SB4 list_submissions_for_assignment
      ok(await teacher.get(`/api/courses/${course_id}/assignments/${assignment_id}/submissions`));

      // FR-UP4 delete_upload
      const deleteUpload = await teacher.delete(`/api/courses/${course_id}/uploads/${upload_id}`);
      ok(deleteUpload);

      const deletedUpload = db
        .prepare('SELECT upload_id FROM uploads WHERE upload_id=?')
        .get(upload_id);
      expect(deletedUpload).toBeUndefined();

      // FR-AS5 delete_assignment
      const deleteAssignment = await teacher.delete(
        `/api/courses/${course_id}/assignments/${assignment_id}`
      );
      ok(deleteAssignment);

      const deletedAssignment = db
        .prepare('SELECT assignment_id FROM assignments WHERE assignment_id=?')
        .get(assignment_id);
      expect(deletedAssignment).toBeUndefined();
    } finally {
      try { db.close(); } catch (_) {}
    }
  });
});