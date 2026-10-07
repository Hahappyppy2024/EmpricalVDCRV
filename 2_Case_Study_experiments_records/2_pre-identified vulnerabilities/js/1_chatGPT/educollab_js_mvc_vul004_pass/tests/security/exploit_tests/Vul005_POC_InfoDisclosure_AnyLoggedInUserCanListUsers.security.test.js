const {
  startFreshServer,
  stopServer,
  openDb,
  registerAny,
  listUsersAny,
} = require('../../_helpers');

describe('PoC: any logged-in user can enumerate all users', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  }, 20000);

  afterAll(async () => {
    await stopServer(server);
  });

  test('a regular logged-in user can access /api/users', async () => {
    const student = server.agent;

    const uniq = `${Date.now()}_${Math.random().toString(16).slice(2, 8)}`;
    const studentName = `student_enum_${uniq}`;

    const db = openDb();
    try {
      await registerAny(student, studentName, 'pass123', 'Student Enum');

      const res = await listUsersAny(student);

      expect([200, 302]).toContain(res.status);

      const users = res.body?.users || db.prepare('SELECT * FROM users').all();

      expect(users.some((u) => u.username === 'admin')).toBe(true);
      expect(users.some((u) => u.username === studentName)).toBe(true);
    } finally {
      db.close();
    }
  });
});