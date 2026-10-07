const fs = require('fs');
const path = require('path');
const stream = require('stream');
const archiver = require('archiver');

const { makeAppWithFreshDb, apiLogin, apiCreateCourse } = require('../_helpers');

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

describe('R08 Integrity: requirement-level features', () => {
  test('SEC-MAT1: Zip Slip entries are ignored (no write outside course dir)', async () => {
    const { agent } = makeAppWithFreshDb('r08_sec_mat1.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    expect([200,201]).toContain(c.status);
    const course_id = c.body.course.course_id;

    const zip = await zipBuffer([
      { name: '../evil.txt', content: 'pwn' },
      { name: 'ok.txt', content: 'safe' }
    ]);

    const up = await agent
      .post(`/api/courses/${course_id}/materials/upload-zip`)
      .attach('zip', zip, { filename: 'evil.zip', contentType: 'application/zip' });

    expect([200,201]).toContain(up.status);

    const dataDir = path.join(__dirname, '..', 'data');
    const outside = path.join(dataDir, 'evil.txt');
    expect(fs.existsSync(outside)).toBe(false);

    const list = await agent.get(`/api/courses/${course_id}/materials/files`);
    expect(list.status).toBe(200);
    const paths = (list.body.files || []).map(f => f.path);
    expect(paths).toContain('ok.txt');
    expect(paths).not.toContain('../evil.txt');
  });

  test('SEC-CSV2: Import ignores invalid roles', async () => {
    const { agent } = makeAppWithFreshDb('r08_sec_csv2.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C', description: 'D' });
    expect([200,201]).toContain(c.status);
    const course_id = c.body.course.course_id;

    const csv = Buffer.from("username,display_name,role_in_course\nmallory,Mallory,hacker\n");
    const imp = await agent.post(`/api/courses/${course_id}/members/import`)
      .attach('csv', csv, { filename: 'm.csv', contentType: 'text/csv' });
    expect(imp.status).toBe(200);

    const list = await agent.get(`/api/courses/${course_id}/members`);
    expect(list.status).toBe(200);
    const usernames = (list.body.members || []).map(m => m.username);
    expect(usernames).not.toContain('mallory');
  });
});
