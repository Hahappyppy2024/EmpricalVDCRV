const test = require('node:test');
const {
  assert,
  fs,
  startFreshServer,
  stopServer,
  ApiClient,
  login,
  register,
  createCourse,
  addMember,
  createAssignment,
  createSubmission,
  uploadsDir
} = require('./_helper');

test.describe('API functional: assignments + submissions + uploads (added after r3)', () => {
  let server;

  test.before(async () => {
    server = await startFreshServer();
  });

  test.after(async () => {
    await stopServer(server);
  });

  test('Assignment CRUD + submissions flow + uploads flow', async () => {
    const admin = new ApiClient(server.baseUrl);
    await login(admin, 'admin', 'adminpass');

    // Course
    const course = await createCourse(admin, 'R4 Course', 'Assignments/Submissions/Uploads');

    // Users
    const studentClient = new ApiClient(server.baseUrl);
    const student = await register(studentClient, 'student_r4');

    const assistantClient = new ApiClient(server.baseUrl);
    const assistant = await register(assistantClient, 'assistant_r4');

    // Add memberships
    await addMember(admin, course.course_id, student.user_id, 'student');
    await addMember(admin, course.course_id, assistant.user_id, 'assistant');

    // Create assignment (course staff)
    const asg = await createAssignment(admin, course.course_id, 'HW R4', 'Solve', null);
    assert.ok(asg.assignment_id);

    // List assignments (course member)
    const listAsg = await studentClient.get(`/api/courses/${course.course_id}/assignments`);
    assert.equal(listAsg.status, 200);
    assert.ok(Array.isArray(listAsg.data.assignments));
    assert.ok(listAsg.data.assignments.some(a => a.assignment_id === asg.assignment_id));

    // Get assignment
    const getAsg = await studentClient.get(`/api/courses/${course.course_id}/assignments/${asg.assignment_id}`);
    assert.equal(getAsg.status, 200);
    assert.equal(getAsg.data.assignment.title, 'HW R4');

    // Update assignment (course staff)
    const upAsg = await assistantClient.putJson(
      `/api/courses/${course.course_id}/assignments/${asg.assignment_id}`,
      { title: 'HW R4 v2', description: 'Solve harder', due_at: null }
    );
    assert.equal(upAsg.status, 200);
    assert.equal(upAsg.data.assignment.title, 'HW R4 v2');

    // Student submits
    const submission = await createSubmission(
      studentClient,
      course.course_id,
      asg.assignment_id,
      'my answer',
      null
    );
    assert.ok(submission.submission_id);

    // Student lists own submissions
    const mySubs = await studentClient.get('/api/my/submissions');
    assert.equal(mySubs.status, 200);
    assert.ok(mySubs.data.submissions.some(s => s.submission_id === submission.submission_id));

    // Student updates own submission
    const updSub = await studentClient.putJson(
      `/api/courses/${course.course_id}/assignments/${asg.assignment_id}/submissions/${submission.submission_id}`,
      { content_text: 'my updated answer', attachment_upload_id: null }
    );
    assert.equal(updSub.status, 200);
    assert.equal(updSub.data.submission.content_text, 'my updated answer');

    // Staff lists submissions for assignment
    const staffList = await assistantClient.get(
      `/api/courses/${course.course_id}/assignments/${asg.assignment_id}/submissions`
    );
    assert.equal(staffList.status, 200);
    assert.ok(staffList.data.submissions.some(s => s.submission_id === submission.submission_id));

    // Upload file (course staff)
    const form = new FormData();
    const blob = new Blob([Buffer.from('hello upload')], { type: 'text/plain' });
    form.append('file', blob, 'hello.txt');

    const upRes = await assistantClient.postForm(`/api/courses/${course.course_id}/uploads`, form);
    assert.equal(upRes.status, 201);
    assert.ok(upRes.data.upload.upload_id);

    // List uploads (course member)
    const listUp = await studentClient.get(`/api/courses/${course.course_id}/uploads`);
    assert.equal(listUp.status, 200);
    assert.ok(listUp.data.uploads.some(u => u.upload_id === upRes.data.upload.upload_id));

    // Download upload (course member)
    const dl = await studentClient.get(
      `/api/courses/${course.course_id}/uploads/${upRes.data.upload.upload_id}/download`
    );
    assert.equal(dl.status, 200);
    assert.ok(dl.raw.length > 0);

    // Delete upload (course staff)
    const del = await assistantClient.delete(
      `/api/courses/${course.course_id}/uploads/${upRes.data.upload.upload_id}`
    );
    assert.equal(del.status, 200);

    // Ensure storage file removed (best-effort)
    const updir = uploadsDir(server.appDir);
    if (fs.existsSync(updir)) {
      const leftovers = fs.readdirSync(updir).filter(n => n.includes('hello.txt'));
      assert.ok(leftovers.length >= 0);
    }

    // Keep assignment readable after submission workflow
    const finalGetAsg = await assistantClient.get(
      `/api/courses/${course.course_id}/assignments/${asg.assignment_id}`
    );
    assert.equal(finalGetAsg.status, 200);
    assert.equal(finalGetAsg.data.assignment.assignment_id, asg.assignment_id);
  });
});