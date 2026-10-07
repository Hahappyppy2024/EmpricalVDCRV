const {
  startFreshServer,
  stopServer,
  openDb,
  loginAny,
  createCourseAny,
  uploadFileAny,
} = require('../../_helpers');

describe('PoC: bypass protected download by directly fetching /uploads/<storage_path>', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  }, 20000);

  afterAll(async () => {
    await stopServer(server);
  });

  test('unauthenticated client can fetch uploaded file from public /uploads static path', async () => {
    const teacher = server.agent;
    const db = openDb();

    // use seeded admin to avoid registration-path instability
    await loginAny(teacher, 'admin', 'admin123');

    const courseRes = await createCourseAny(teacher, 'Course Upload 1', 'Desc');
    const courseId =
      courseRes.body?.course?.course_id ||
      db.prepare('SELECT course_id FROM courses ORDER BY course_id DESC LIMIT 1').get().course_id;

    const uploadRes = await uploadFileAny(
      teacher,
      courseId,
      'secret.txt',
      'TOP_SECRET_CONTENT',
      'text/plain'
    );

    const storagePath =
      uploadRes.body?.upload?.storage_path ||
      db.prepare('SELECT storage_path FROM uploads ORDER BY upload_id DESC LIMIT 1').get().storage_path;

    const anon = server.request;
    const res = await anon.get(`/uploads/${storagePath}`);

    expect(res.status).toBe(200);
    expect(res.text).toContain('TOP_SECRET_CONTENT');
  });
});