const { startFreshServer, stopServer, openDb, ApiClient } = require('./api_helpers');

jest.setTimeout(30000);

function ok(res) {
  expect([200, 201, 302]).toContain(res.status);
}

function getUserId(db, username) {
  const row = db.prepare('SELECT user_id FROM users WHERE username=?').get(username);
  return row && row.user_id;
}

describe('Requirement 03: Post + Comment + Search', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  });

  afterAll(async () => {
    await stopServer(server);
  });

  test('course members can create, view, update, search, and delete posts/comments', async () => {
    const admin = new ApiClient(server.baseUrl);
    const member = new ApiClient(server.baseUrl);
    const db = openDb();

    try {
      ok(await admin.post('/api/auth/login', {
        json: { username: 'admin', password: 'admin123' },
      }));

      ok(await member.post('/api/auth/register', {
        json: {
          username: 'req3_member',
          password: 'pass123',
          display_name: 'Req3 Member',
        },
      }));

      const memberId = getUserId(db, 'req3_member');
      expect(memberId).toBeTruthy();

      ok(await admin.post('/api/courses', {
        json: {
          title: 'Req3 Course',
          description: 'Post comment search course',
        },
      }));

      const courseRow = db
        .prepare('SELECT course_id FROM courses WHERE title=? ORDER BY course_id DESC')
        .get('Req3 Course');
      expect(courseRow && courseRow.course_id).toBeTruthy();
      const course_id = courseRow.course_id;

      ok(await admin.post(`/api/courses/${course_id}/members`, {
        json: {
          user_id: memberId,
          role_in_course: 'student',
        },
      }));

      // FR-P1 create_post
      const createPost = await member.post(`/api/courses/${course_id}/posts`, {
        json: {
          title: 'Req3 Post',
          body: 'This is a searchable post body',
        },
      });
      ok(createPost);

      const postRow = db
        .prepare('SELECT post_id, title, body FROM posts WHERE course_id=? AND author_id=? ORDER BY post_id DESC')
        .get(course_id, memberId);
      expect(postRow && postRow.post_id).toBeTruthy();
      const post_id = postRow.post_id;

      // FR-P2 list_posts
      ok(await member.get(`/api/courses/${course_id}/posts`));

      // FR-P3 get_post
      ok(await member.get(`/api/courses/${course_id}/posts/${post_id}`));

      // FR-P4 update_post
      const updatePost = await member.put(`/api/courses/${course_id}/posts/${post_id}`, {
        json: {
          title: 'Req3 Post Updated',
          body: 'Updated searchable post body',
        },
      });
      ok(updatePost);

      const updatedPost = db
        .prepare('SELECT title, body FROM posts WHERE post_id=?')
        .get(post_id);
      expect(updatedPost.title).toBe('Req3 Post Updated');
      expect(updatedPost.body).toBe('Updated searchable post body');

      // FR-CM1 create_comment
      const createComment = await member.post(
        `/api/courses/${course_id}/posts/${post_id}/comments`,
        { json: { body: 'Req3 comment text' } }
      );
      ok(createComment);

      const commentRow = db
        .prepare('SELECT comment_id, body FROM comments WHERE course_id=? AND post_id=? AND author_id=? ORDER BY comment_id DESC')
        .get(course_id, post_id, memberId);
      expect(commentRow && commentRow.comment_id).toBeTruthy();
      const comment_id = commentRow.comment_id;

      // FR-CM2 list_comments
      ok(await member.get(`/api/courses/${course_id}/posts/${post_id}/comments`));

      // FR-CM3 update_comment
      const updateComment = await member.put(
        `/api/courses/${course_id}/posts/${post_id}/comments/${comment_id}`,
        { json: { body: 'Req3 comment updated' } }
      );
      ok(updateComment);

      const updatedComment = db
        .prepare('SELECT body FROM comments WHERE comment_id=?')
        .get(comment_id);
      expect(updatedComment.body).toBe('Req3 comment updated');

      // FR-S1 search_posts
      ok(await member.get(`/api/courses/${course_id}/search/posts?keyword=Updated`));

      // FR-S2 search_comments
      ok(await member.get(`/api/courses/${course_id}/search/comments?keyword=updated`));

      // FR-CM4 delete_comment
      const deleteComment = await member.delete(
        `/api/courses/${course_id}/posts/${post_id}/comments/${comment_id}`
      );
      ok(deleteComment);

      const deletedComment = db
        .prepare('SELECT comment_id FROM comments WHERE comment_id=?')
        .get(comment_id);
      expect(deletedComment).toBeUndefined();

      // FR-P5 delete_post
      const deletePost = await member.delete(`/api/courses/${course_id}/posts/${post_id}`);
      ok(deletePost);

      const deletedPost = db
        .prepare('SELECT post_id FROM posts WHERE post_id=?')
        .get(post_id);
      expect(deletedPost).toBeUndefined();
    } finally {
      try { db.close(); } catch (_) {}
    }
  });
});