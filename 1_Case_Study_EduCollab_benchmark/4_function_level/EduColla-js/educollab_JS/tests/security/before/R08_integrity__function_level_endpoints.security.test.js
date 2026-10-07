const fs = require('fs');
const path = require('path');
const stream = require('stream');
const archiver = require('archiver');
const request = require('supertest');

const { makeAppWithFreshDb, apiLogin, apiCreateCourse, apiCreateAssignment } = require('../_helpers');

function zipBuffer(entries) {
  return new Promise((resolve, reject) => {
    const archive = archiver('zip', { zlib: { level: 9 } });
    const bufs = [];
    const pass = new stream.PassThrough();
    pass.on('data', (d) => bufs.push(d));
    pass.on('end', () => resolve(Buffer.concat(bufs)));
    archive.on('error', reject);
    archive.pipe(pass);
    for (const e of entries) archive.append(e.content, { name: e.name });
    archive.finalize();
  });
}

describe('R08 Integrity: function-level endpoints', () => {
  test('SEC-FL1: Zip Slip in submission unzip is ignored', async () => {
    const { app, agent } = makeAppWithFreshDb('r08_sec_fl1.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await agent.post('/api/courses').send({ title: 'C', description: 'D' });
    expect([200,201]).toContain(c.status);
    const course_id = c.body.course.course_id;

    const roster = Buffer.from("username,display_name,role_in_course\nstu,Student,student\n");
    await agent.post(`/api/courses/${course_id}/members/import`)
      .attach('csv', roster, { filename: 'm.csv', contentType: 'text/csv' });

    const asg = await apiCreateAssignment(agent, course_id);
    expect([200,201]).toContain(asg.status);
    const assignment_id = asg.body.assignment.assignment_id;

    const zip = await zipBuffer([{ name: '../evil.txt', content: 'pwn' }, { name: 'ok.txt', content: 'safe' }]);
    const up = await agent.post(`/api/courses/${course_id}/uploads`)
      .attach('file', zip, { filename: 'attachment.zip', contentType: 'application/zip' });
    expect([200,201]).toContain(up.status);
    const upload_id = up.body.upload.upload_id;

    const student = request.agent(app);
    await apiLogin(student, { username: 'stu', password: 'password' });
    const sub = await student.post(`/api/courses/${course_id}/assignments/${assignment_id}/submissions`)
      .send({ content_text: 'x', attachment_upload_id: upload_id });
    expect([200,201]).toContain(sub.status);
    const submission_id = sub.body.submission.submission_id;

    const uz = await agent.post(`/api/courses/${course_id}/assignments/${assignment_id}/submissions/${submission_id}/unzip`);
    expect(uz.status).toBe(200);

    const outside = path.join(__dirname, '..', 'data', 'evil.txt');
    expect(fs.existsSync(outside)).toBe(false);

    const list = await agent.get(`/api/courses/${course_id}/assignments/${assignment_id}/submissions/${submission_id}/files`);
    expect(list.status).toBe(200);
    const paths = (list.body.files || []).map(f => f.path);
    expect(paths).toContain('ok.txt');
    expect(paths).not.toContain('../evil.txt');
  });

  test('SEC-FL2: export download endpoint blocks traversal export_id', async () => {
    const { agent } = makeAppWithFreshDb('r08_sec_fl2.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    expect([200,201]).toContain(c.status);
    const course_id = c.body.course.course_id;
    const asg = await apiCreateAssignment(agent, course_id);
    expect([200,201]).toContain(asg.status);
    const assignment_id = asg.body.assignment.assignment_id;

    const bad = await agent.get(`/api/courses/${course_id}/assignments/${assignment_id}/submissions/export/../../app/download`);
    expect([400,404]).toContain(bad.status);
  });

  test('SEC-FL3: grades import clamps non-numeric and does not update', async () => {
    const { app, agent } = makeAppWithFreshDb('r08_sec_fl3.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    expect([200,201]).toContain(c.status);
    const course_id = c.body.course.course_id;

    const roster = Buffer.from("username,display_name,role_in_course\nstu,Student,student\n");
    await agent.post(`/api/courses/${course_id}/members/import`)
      .attach('csv', roster, { filename: 'm.csv', contentType: 'text/csv' });

    const asg = await apiCreateAssignment(agent, course_id);
    expect([200,201]).toContain(asg.status);
    const assignment_id = asg.body.assignment.assignment_id;

    const student = request.agent(app);
    await apiLogin(student, { username: 'stu', password: 'password' });
    const sub = await student.post(`/api/courses/${course_id}/assignments/${assignment_id}/submissions`).send({ content_text: 'my' });
    expect([200,201]).toContain(sub.status);
    const submission_id = sub.body.submission.submission_id;

    const grades = Buffer.from(`submission_id,score,feedback\n${submission_id},not_a_number,ok\n`);
    const imp = await agent.post(`/api/courses/${course_id}/assignments/${assignment_id}/grades/import`)
      .attach('csv', grades, { filename: 'g.csv', contentType: 'text/csv' });
    expect(imp.status).toBe(200);
    expect(imp.body.updatedCount).toBe(0);
  });
});
