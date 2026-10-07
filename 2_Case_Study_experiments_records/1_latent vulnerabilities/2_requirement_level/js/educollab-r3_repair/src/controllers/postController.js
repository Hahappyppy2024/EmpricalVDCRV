const courseRepository = require('../repositories/courseRepository');
const postRepository = require('../repositories/postRepository');
const commentRepository = require('../repositories/commentRepository');

const STAFF_ROLES = new Set(['teacher', 'admin', 'assistant', 'senior-assistant']);

function isCourseStaff(membership) {
  return !!(membership && STAFF_ROLES.has(membership.role_in_course));
}

function isAuthorOrStaff(currentUser, resourceAuthorId, membership) {
  return (currentUser && String(currentUser.user_id) === String(resourceAuthorId)) || isCourseStaff(membership);
}

function ensureCourse(courseId) {
  return courseRepository.findById(courseId);
}

function ensurePost(courseId, postId) {
  const post = postRepository.findById(postId);
  if (!post || String(post.course_id) !== String(courseId)) {
    return null;
  }
  return post;
}

function showNewPost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }
  return res.render('post_new', { course, error: null });
}

function createPost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const { title, body } = req.body;
  if (!title) {
    return res.status(400).render('post_new', { course, error: 'Title is required.' });
  }

  const post = postRepository.createPost({
    course_id: req.params.course_id,
    author_id: req.currentUser.user_id,
    title,
    body: body || ''
  });

  return res.redirect(`/courses/${req.params.course_id}/posts/${post.post_id}`);
}

function apiCreatePost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const { title, body } = req.body;
  if (!title) {
    return res.status(400).json({ success: false, error: 'title is required' });
  }

  const post = postRepository.createPost({
    course_id: req.params.course_id,
    author_id: req.currentUser.user_id,
    title,
    body: body || ''
  });

  return res.status(201).json({ success: true, post });
}

function listPosts(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const posts = postRepository.listByCourse(req.params.course_id);
  return res.render('posts_list', { course, posts });
}

function apiListPosts(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const posts = postRepository.listByCourse(req.params.course_id);
  return res.json({ success: true, posts });
}

function getPost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).send('Post not found');
  }

  const comments = commentRepository.listByPost(req.params.post_id);
  return res.render('post_show', { course, post, comments });
}

function apiGetPost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }

  return res.json({ success: true, post });
}

function showEditPost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).send('Post not found');
  }

  if (!isAuthorOrStaff(req.currentUser, post.author_id, req.courseMembership)) {
    return res.status(403).send('Forbidden');
  }

  return res.render('post_edit', { course, post, error: null });
}

function updatePost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const existing = ensurePost(req.params.course_id, req.params.post_id);
  if (!existing) {
    return res.status(404).send('Post not found');
  }

  const staff = isCourseStaff(req.courseMembership);
  const isAuthor = req.currentUser && String(req.currentUser.user_id) === String(existing.author_id);
  if (!staff && !isAuthor) {
    return res.status(403).send('Forbidden');
  }

  const { title, body } = req.body;
  if (!title) {
    return res.status(400).render('post_edit', { course, post: existing, error: 'Title is required.' });
  }

  const updated = staff
    ? postRepository.updatePost(req.params.post_id, { title, body: body || '' })
    : postRepository.updatePostAsAuthor(req.params.post_id, req.currentUser.user_id, { title, body: body || '' });

  if (!updated) {
    return res.status(403).send('Forbidden');
  }

  return res.redirect(`/courses/${req.params.course_id}/posts/${req.params.post_id}`);
}

function apiUpdatePost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const existing = ensurePost(req.params.course_id, req.params.post_id);
  if (!existing) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }

  const staff = isCourseStaff(req.courseMembership);
  const isAuthor = req.currentUser && String(req.currentUser.user_id) === String(existing.author_id);
  if (!staff && !isAuthor) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }

  const { title, body } = req.body;
  if (!title) {
    return res.status(400).json({ success: false, error: 'title is required' });
  }

  const post = staff
    ? postRepository.updatePost(req.params.post_id, { title, body: body || '' })
    : postRepository.updatePostAsAuthor(req.params.post_id, req.currentUser.user_id, { title, body: body || '' });

  if (!post) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }

  return res.json({ success: true, post });
}

function deletePost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).send('Post not found');
  }

  const staff = isCourseStaff(req.courseMembership);
  const isAuthor = req.currentUser && String(req.currentUser.user_id) === String(post.author_id);
  if (!staff && !isAuthor) {
    return res.status(403).send('Forbidden');
  }

  const deleted = staff
    ? postRepository.deletePost(req.params.post_id)
    : postRepository.deletePostAsAuthor(req.params.post_id, req.currentUser.user_id);

  if (deleted && deleted.changes === 0) {
    return res.status(403).send('Forbidden');
  }
  return res.redirect(`/courses/${req.params.course_id}/posts`);
}

function apiDeletePost(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }

  const staff = isCourseStaff(req.courseMembership);
  const isAuthor = req.currentUser && String(req.currentUser.user_id) === String(post.author_id);
  if (!staff && !isAuthor) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }

  const deleted = staff
    ? postRepository.deletePost(req.params.post_id)
    : postRepository.deletePostAsAuthor(req.params.post_id, req.currentUser.user_id);

  if (deleted && deleted.changes === 0) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }
  return res.json({ success: true });
}

function listComments(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).send('Post not found');
  }

  const comments = commentRepository.listByPost(req.params.post_id);
  return res.render('comments_list', { course, post, comments });
}

function apiListComments(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }

  const comments = commentRepository.listByPost(req.params.post_id);
  return res.json({ success: true, comments });
}

function searchPosts(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const keyword = req.query.keyword || '';
  const posts = postRepository.searchByCourse(req.params.course_id, keyword);
  return res.render('search_posts_results', { course, keyword, posts });
}

function apiSearchPosts(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const keyword = req.query.keyword || '';
  const posts = postRepository.searchByCourse(req.params.course_id, keyword);
  return res.json({ success: true, keyword, posts });
}

module.exports = {
  showNewPost,
  createPost,
  apiCreatePost,
  listPosts,
  apiListPosts,
  getPost,
  apiGetPost,
  showEditPost,
  updatePost,
  apiUpdatePost,
  deletePost,
  apiDeletePost,
  listComments,
  apiListComments,
  searchPosts,
  apiSearchPosts
};