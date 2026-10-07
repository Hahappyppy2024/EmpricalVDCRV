const {
  startFreshServer,
  stopServer,
  openDb,
  registerAny,
  createCourseAny,
  uploadFileAny,
} = require('../../_helpers');

describe('PoC: upload HTML/JS and retrieve it from same origin', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  }, 20000);

  afterAll(async () => {
    await stopServer(server);
  });

  test('uploaded .html is publicly retrievable as active content from the application origin', async () => {
    const teacher = server.agent;

    const uniq = `${Date.now()}_${Math.random().toString(16).slice(2, 8)}`;
    const teacherName = `teacher_up_${uniq}`;

    const db = openDb();
    try {
      await registerAny(teacher, teacherName, 'pass123', 'Teacher Up 2');

      const courseRes = await createCourseAny(teacher, 'Course Upload 2', 'Desc');
      const courseId =
        courseRes.body?.course?.course_id ||
        db.prepare('SELECT course_id FROM courses ORDER BY course_id DESC LIMIT 1').get().course_id;

      const payload = '<html><body><script>window.__XSS_POC__=1;</script>owned</body></html>';
      const uploadRes = await uploadFileAny(
        teacher,
        courseId,
        'poc.html',
        payload,
        'text/html'
      );

      const storagePath =
        uploadRes.body?.upload?.storage_path ||
        db.prepare('SELECT storage_path FROM uploads ORDER BY upload_id DESC LIMIT 1').get().storage_path;

      const res = await server.request.get(`/uploads/${storagePath}`);

      expect(res.status).toBe(200);
      expect(res.text).toContain('<script>window.__XSS_POC__=1;</script>');
    } finally {
      db.close();
    }
  });
});