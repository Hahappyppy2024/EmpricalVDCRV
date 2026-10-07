const test = require('node:test');
const { assert, startFreshServer, stopServer, ApiClient, registerAndLogin } = require('./_helper');

test('API functional: posts/comments/search flow + membership gating', async (t) => {
  const server = await startFreshServer();
  t.after(async () => stopServer(server));

  const teacher = new ApiClient(server.baseUrl);
  const student = new ApiClient(server.baseUrl);
  const outsider = new ApiClient(server.baseUrl);

  const teacherUser = await registerAndLogin(teacher, 'teacher_p1');
  const studentUser = await registerAndLogin(student, 'student_p1');
  await registerAndLogin(outsider, 'outsider_p1');

  // Teacher creates a course (teacher auto-added as teacher)
  const createCourse = await teacher.post('/api/courses', {
    title: 'Posts Course',
    description: 'C'
  });
  assert.equal(createCourse.status, 201);
  const courseId = createCourse.data.course.course_id;

  // Teacher adds student to course
  const addMember = await teacher.post(`/api/courses/${courseId}/members`, {
    user_id: studentUser.user_id,
    role_in_course: 'student'
  });
  assert.equal(addMember.status, 201);

  // Outsider cannot access posts API for this course
  const outsiderList = await outsider.get(`/api/courses/${courseId}/posts`);
  assert.equal(outsiderList.status, 403);

  // Teacher creates a post
  const createPost = await teacher.post(`/api/courses/${courseId}/posts`, {
    title: 'Hello Post',
    body: 'Body 123'
  });
  assert.equal(createPost.status, 201);
  assert.equal(createPost.data.success, true);
  const postId = createPost.data.post.post_id;

  // Student can list and get the post
  const listPosts = await student.get(`/api/courses/${courseId}/posts`);
  assert.equal(listPosts.status, 200);
  assert.equal(listPosts.data.success, true);
  assert.ok(Array.isArray(listPosts.data.posts));
  assert.ok(listPosts.data.posts.some(p => String(p.post_id) === String(postId)));

  const getPost = await student.get(`/api/courses/${courseId}/posts/${postId}`);
  assert.equal(getPost.status, 200);
  assert.equal(getPost.data.post.title, 'Hello Post');

  // Student comments on the post
  const createComment = await student.post(`/api/courses/${courseId}/posts/${postId}/comments`, {
    body: 'Nice!'
  });
  assert.equal(createComment.status, 201);
  const commentId = createComment.data.comment.comment_id;

  // List comments via API route (wired to postController.apiListComments)
  const listComments = await student.get(`/api/courses/${courseId}/posts/${postId}/comments`);
  assert.equal(listComments.status, 200);
  assert.equal(listComments.data.success, true);
  assert.ok(Array.isArray(listComments.data.comments));
  assert.ok(listComments.data.comments.some(c => String(c.comment_id) === String(commentId)));

  // Author updates own post
  const updatePost = await teacher.put(`/api/courses/${courseId}/posts/${postId}`, {
    title: 'Hello Post (edited)',
    body: 'Body 456'
  });
  assert.equal(updatePost.status, 200);
  assert.equal(updatePost.data.post.title, 'Hello Post (edited)');

  // Author updates own comment
  const updateComment = await student.put(`/api/courses/${courseId}/posts/${postId}/comments/${commentId}`, {
    body: 'Nice!! (edited)'
  });
  assert.equal(updateComment.status, 200);
  assert.equal(updateComment.data.comment.body, 'Nice!! (edited)');

  // Search posts
  const searchPosts = await student.get(`/api/courses/${courseId}/search/posts?keyword=edited`);
  assert.equal(searchPosts.status, 200);
  assert.equal(searchPosts.data.success, true);
  assert.ok(searchPosts.data.posts.some(p => String(p.post_id) === String(postId)));

  // Search comments
  const searchComments = await student.get(`/api/courses/${courseId}/search/comments?keyword=Nice`);
  assert.equal(searchComments.status, 200);
  assert.equal(searchComments.data.success, true);
  assert.ok(searchComments.data.comments.some(c => String(c.comment_id) === String(commentId)));

  // Delete comment
  const delComment = await student.delete(`/api/courses/${courseId}/posts/${postId}/comments/${commentId}`);
  assert.equal(delComment.status, 200);
  assert.equal(delComment.data.success, true);

  const listCommentsAfter = await student.get(`/api/courses/${courseId}/posts/${postId}/comments`);
  assert.equal(listCommentsAfter.status, 200);
  assert.ok(!listCommentsAfter.data.comments.some(c => String(c.comment_id) === String(commentId)));

  // Delete post
  const delPost = await teacher.delete(`/api/courses/${courseId}/posts/${postId}`);
  assert.equal(delPost.status, 200);
  assert.equal(delPost.data.success, true);

  const listPostsAfter = await student.get(`/api/courses/${courseId}/posts`);
  assert.equal(listPostsAfter.status, 200);
  assert.ok(!listPostsAfter.data.posts.some(p => String(p.post_id) === String(postId)));
});
