const { makeAppWithFreshDb, apiRegister, apiLogin, apiCreateCourse } = require('../_helpers');
const { addMemberByUsername } = require('./_pl_utils');

describe('Project-level: Uploads', () => {
  test('staff upload + download', async () => {
    const { agent } = makeAppWithFreshDb('func_upload.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    const course_id = c.body.course.course_id;

    const buf = Buffer.from('hello');
    const up = await agent.post(`/api/courses/${course_id}/uploads`)
      .attach('file', buf, { filename: 'note.txt', contentType: 'text/plain' });

    expect([200,201]).toContain(up.status);
    const upload_id = up.body.upload.upload_id;

    const dl = await agent.get(`/api/courses/${course_id}/uploads/${upload_id}/download`);
    expect(dl.status).toBe(200);
    expect(dl.text).toBe('hello');
  });
});
