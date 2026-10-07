const {
  makeAppWithFreshDb,
  apiLogin,
  apiCreateCourse,
  apiCreateAssignment
} = require('../_helpers');

const { zipBuffer, importRosterCsvAsAdmin } = require('./_fl_utils');


describe('Function-level FL-FR1/2', () => {
  test('unzip submission zip and list extracted files', async () => {
    const { app, request, agent } = makeAppWithFreshDb('fl1.db');

    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    expect(c.status).toBe(201);
    const course_id = c.body.course.course_id;

    const imp = await importRosterCsvAsAdmin(
      agent,
      course_id,
      "username,display_name,role_in_course\nstu,Student,student\n"
    );
    expect([200, 201]).toContain(imp.status);

    const asg = await apiCreateAssignment(agent, course_id);
    expect(asg.status).toBe(201);
    const assignment_id = asg.body.assignment.assignment_id;

    const zip = await zipBuffer([
      { name: 'hello.txt', content: 'hi' },
      { name: 'dir/a.txt', content: 'a' }
    ]);
    const up = await agent.post(`/api/courses/${course_id}/uploads`)
      .attach('file', zip, { filename: 'attachment.zip', contentType: 'application/zip' });
    expect(up.status).toBe(201);
    const upload_id = up.body.upload.upload_id;

    const student = request.agent(app);
    await apiLogin(student, { username: 'stu', password: 'password' });
    const sub = await student
      .post(`/api/courses/${course_id}/assignments/${assignment_id}/submissions`)
      .send({ content_text: 'my', attachment_upload_id: upload_id });
    expect(sub.status).toBe(201);
    const submission_id = sub.body.submission.submission_id;

    const uz = await agent.post(
      `/api/courses/${course_id}/assignments/${assignment_id}/submissions/${submission_id}/unzip`
    );
    if (![200, 201].includes(uz.status)) {
      throw new Error(`unzip failed: status=${uz.status} body=${JSON.stringify(uz.body)} text=${uz.text}`);
    }

    const list = await agent.get(
      `/api/courses/${course_id}/assignments/${assignment_id}/submissions/${submission_id}/files`
    );
    expect(list.status).toBe(200);
    const paths = list.body.files.map(f => f.path);
    expect(paths).toContain('hello.txt');
    expect(paths).toContain('dir/a.txt');
  });
});
