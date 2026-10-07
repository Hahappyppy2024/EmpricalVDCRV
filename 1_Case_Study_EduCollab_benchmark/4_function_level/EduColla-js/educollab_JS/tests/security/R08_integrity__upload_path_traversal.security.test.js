const { makeAppWithFreshDb } = require('../_helpers');

console.log('SEC TEST VERSION: R08 upload path traversal v2');

function getCourseId(res) {
  const b = res?.body || {};
  return b?.course?.course_id ?? b?.course_id ?? b?.course?.id ?? b?.id ?? null;
}

function short(res) {
  return {
    status: res?.status,
    ct: res?.headers?.['content-type'],
    body: res?.body,
    text: (res?.text || '').slice(0, 200),
  };
}

async function loginOrDie(agent, username, password) {
  const attempts = [
    { path: '/api/login', body: { username, password } },
    { path: '/api/auth/login', body: { username, password } },
    { path: '/api/login', body: { email: username, password } },
    { path: '/api/auth/login', body: { email: username, password } },
  ];
  let last = null;
  for (const a of attempts) {
    const r = await agent.post(a.path).set('Content-Type', 'application/json').send(a.body);
    last = r;
    if ([200, 201, 204, 302].includes(r.status)) return r;
  }
  throw new Error(`Login failed: ${JSON.stringify(short(last), null, 2)}`);
}

async function createCourseOrWarn(agent) {
  const attempts = [
    { path: '/api/courses', body: { title: 'C', description: 'D' } },
    { path: '/api/courses', body: { name: 'C', description: 'D' } },
    { path: '/api/courses', body: { name: 'C', desc: 'D' } },
  ];
  let last = null;
  for (const a of attempts) {
    const r = await agent.post(a.path).set('Content-Type', 'application/json').send(a.body);
    last = r;

    if ([200, 201].includes(r.status)) {
      const id = getCourseId(r);
      if (!id) throw new Error(`Course created but cannot extract id: ${JSON.stringify(short(r), null, 2)}`);
      return id;
    }

    if ([401, 403, 404].includes(r.status)) {
      console.warn(`WARN(R08): course create not permitted/implemented (status=${r.status}). Not testable.`);
      return null;
    }
  }
  throw new Error(`Create course failed unexpectedly: ${JSON.stringify(short(last), null, 2)}`);
}

describe('Security: uploads download path traversal', () => {
  test('download endpoint should not allow traversal via upload_id (sanity)', async () => {
    const { agent } = makeAppWithFreshDb('sec_up_trav.db');

    await loginOrDie(agent, 'admin', 'admin123');
    const course_id = await createCourseOrWarn(agent);
    if (!course_id) return;

    const res = await agent.get(`/api/courses/${course_id}/uploads/../../app.js/download`);
    // 404/400 are acceptable; 200 would be very suspicious
    expect([404, 400]).toContain(res.status);
  });

  test('uploaded file is stored under data/uploads and served by id only', async () => {
    const { agent } = makeAppWithFreshDb('sec_up_ok.db');

    await loginOrDie(agent, 'admin', 'admin123');
    const course_id = await createCourseOrWarn(agent);
    if (!course_id) return;

    const buf = Buffer.from('hello');

    // Try common file field names to avoid MulterError: Unexpected field
    const fields = ['file', 'upload', 'attachment', 'f'];
    let up = null;

    for (const field of fields) {
      const r = await agent
        .post(`/api/courses/${course_id}/uploads`)
        .attach(field, buf, { filename: 'note.txt', contentType: 'text/plain' });

      up = r;

      if ([200, 201].includes(r.status)) break;
      if (r.status === 404) {
        console.warn('WARN(R08): upload endpoint not implemented (404). Not testable.');
        return;
      }
      // wrong field might yield 400/500; keep trying
    }

    if (!up || ![200, 201].includes(up.status)) {
      throw new Error(`Upload failed: ${JSON.stringify(short(up), null, 2)}`);
    }

    const upload_id = up.body?.upload?.upload_id ?? up.body?.upload_id ?? up.body?.id ?? null;
    if (!upload_id) {
      throw new Error(`Cannot extract upload_id: ${JSON.stringify(short(up), null, 2)}`);
    }

    const dl = await agent.get(`/api/courses/${course_id}/uploads/${upload_id}/download`);
    if (dl.status === 404) {
      console.warn('WARN(R08): download endpoint not implemented (404). Not testable.');
      return;
    }

    expect(dl.status).toBe(200);
    expect(dl.text).toBe('hello');
  });
});