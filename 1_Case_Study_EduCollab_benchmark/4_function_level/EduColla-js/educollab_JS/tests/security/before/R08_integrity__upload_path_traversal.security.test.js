
const path = require('path');
const fs = require('fs');
const { makeAppWithFreshDb, apiLogin, apiCreateCourse } = require('../_helpers');

describe('Security: uploads download path traversal', () => {
  test('download endpoint should not allow traversal via upload_id (sanity)', async () => {
    const { agent } = makeAppWithFreshDb('sec_up_trav.db');
    await apiLogin(agent, 'admin', 'admin123');
    const c = await apiCreateCourse(agent, 'C', 'D');
    const course_id = c.body.course.course_id;

    // Try using a non-numeric upload_id; router params are numeric -> likely 404
    const res = await agent.get(`/api/courses/${course_id}/uploads/../../app.js/download`);
    expect([404, 400]).toContain(res.status);
  });

  test('uploaded file is stored under data/uploads and served by id only', async () => {
    const { agent } = makeAppWithFreshDb('sec_up_ok.db');
    await apiLogin(agent, 'admin', 'admin123');
    const c = await apiCreateCourse(agent, 'C', 'D');
    const course_id = c.body.course.course_id;

    const buf = Buffer.from('hello');
    const up = await agent.post(`/api/courses/${course_id}/uploads`)
      .attach('file', buf, { filename: 'note.txt', contentType: 'text/plain' });

    expect([201, 200]).toContain(up.status);
    const upload_id = up.body.upload.upload_id;

    const dl = await agent.get(`/api/courses/${course_id}/uploads/${upload_id}/download`);
    expect(dl.status).toBe(200);
    expect(dl.text).toBe('hello');
  });
});
