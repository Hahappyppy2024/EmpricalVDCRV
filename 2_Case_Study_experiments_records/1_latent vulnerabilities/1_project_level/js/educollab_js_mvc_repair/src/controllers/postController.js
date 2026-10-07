const postRepo = require('../repos/postRepo');
const commentRepo = require('../repos/commentRepo');

const staffRoles = new Set(['teacher', 'assistant', 'senior-assistant', 'admin']);

function isCourseStaff(req) {
  const me = req.session.user;
  if (me && me.username === 'admin') return true;
  const role = (req.courseMembership && req.courseMembership.role_in_course) || '';
  return staffRoles.has(role);
}

function deny(req, res, status, apiMsg) {
  if (req.path.startsWith('/api')) return res.status(status).json({ error: apiMsg });
  if (status === 404) return res.status(404).render('404');
  return res.status(status).render('403');
}

function newPostForm(req, res) {
  res.render('posts/new', { course_id: Number(req.params.course_id) });
}

function createPost(req, res) {
  const course_id = Number(req.params.course_id);
  const { title, body } = req.body;
  const me = req.session.user;
  const post = postRepo.createPost({ course_id, author_id: me.user_id, title, body });
  if (req.path.startsWith('/api')) return res.status(201).json({ post });
  res.redirect(`/courses/${course_id}/posts/${post.post_id}`);
}

function listPosts(req, res) {
  const course_id = Number(req.params.course_id);
  const posts = postRepo.listPosts(course_id);
  if (req.path.startsWith('/api')) return res.json({ posts });
  res.render('posts/list', { course_id, posts });
}

function getPost(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const post = postRepo.getById(course_id, post_id);
  if (!post) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'post not found' });
    return res.status(404).render('404');
  }
  const comments = commentRepo.listComments(course_id, post_id);
  if (req.path.startsWith('/api')) return res.json({ post, comments });
  res.render('posts/detail', { course_id, post, comments });
}

function editPostForm(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const post = postRepo.getById(course_id, post_id);
  if (!post) return res.status(404).render('404');
  res.render('posts/edit', { course_id, post });
}

function updatePost(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const { title, body } = req.body;

  const me = req.session.user;
  const existing = postRepo.getById(course_id, post_id);
  if (!existing) return deny(req, res, 404, 'post not found');

  const staff = isCourseStaff(req);
  if (!staff && Number(existing.author_id) !== Number(me.user_id)) {
    return deny(req, res, 403, 'Forbidden');
  }

  const post = staff
    ? postRepo.updatePost(course_id, post_id, { title, body })
    : postRepo.updatePostAsAuthor(course_id, post_id, me.user_id, { title, body });

  if (!post) return deny(req, res, 404, 'post not found');

  if (req.path.startsWith('/api')) return res.json({ post });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

function deletePost(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);

  const me = req.session.user;
  const existing = postRepo.getById(course_id, post_id);
  if (!existing) return deny(req, res, 404, 'post not found');

  const staff = isCourseStaff(req);
  if (!staff && Number(existing.author_id) !== Number(me.user_id)) {
    return deny(req, res, 403, 'Forbidden');
  }

  if (staff) postRepo.deletePost(course_id, post_id);
  else postRepo.deletePostAsAuthor(course_id, post_id, me.user_id);

  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/posts`);
}

module.exports = { newPostForm, createPost, listPosts, getPost, editPostForm, updatePost, deletePost };