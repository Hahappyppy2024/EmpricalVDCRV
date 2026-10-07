const postRepo = require('../repos/postRepo');
const commentRepo = require('../repos/commentRepo');
const membershipRepo = require('../repos/membershipRepo');

const STAFF_ROLES = new Set(['teacher', 'assistant', 'senior-assistant', 'admin']);

function isApi(req) {
  return req.path.startsWith('/api');
}

function deny(req, res) {
  if (isApi(req)) return res.status(403).json({ error: 'forbidden' });
  return res.status(403).render('403');
}

function getMembershipForUser(course_id, user_id) {
  if (!membershipRepo) return null;

  if (typeof membershipRepo.getMembership === 'function') {
    return membershipRepo.getMembership(course_id, user_id);
  }
  if (typeof membershipRepo.getByCourseAndUser === 'function') {
    return membershipRepo.getByCourseAndUser(course_id, user_id);
  }
  if (typeof membershipRepo.getByUserCourse === 'function') {
    return membershipRepo.getByUserCourse(user_id, course_id);
  }
  if (typeof membershipRepo.findByCourseUser === 'function') {
    return membershipRepo.findByCourseUser(course_id, user_id);
  }
  if (typeof membershipRepo.listMembers === 'function') {
    const members = membershipRepo.listMembers(course_id) || [];
    return members.find((m) => Number(m.user_id) === Number(user_id)) || null;
  }

  return null;
}

function canManagePost(course_id, post, me) {
  if (!post || !me) return false;
  if (Number(post.author_id) === Number(me.user_id)) return true;
  if (me.username === 'admin') return true;
  const membership = getMembershipForUser(course_id, me.user_id);
  const role = membership && membership.role_in_course;
  return STAFF_ROLES.has(role);
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
  if (!post) {
    if (isApi(req)) return res.status(404).json({ error: 'post not found' });
    return res.status(404).render('404');
  }
  if (!canManagePost(course_id, post, req.session.user)) return deny(req, res);

  res.render('posts/edit', { course_id, post });
}

function updatePost(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const { title, body } = req.body;

  const existing = postRepo.getById(course_id, post_id);
  if (!existing) {
    if (isApi(req)) return res.status(404).json({ error: 'post not found' });
    return res.status(404).render('404');
  }
  if (!canManagePost(course_id, existing, req.session.user)) return deny(req, res);

  const post = postRepo.updatePost(course_id, post_id, { title, body });
  if (isApi(req)) return res.json({ post });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

function deletePost(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const existing = postRepo.getById(course_id, post_id);
  if (!existing) {
    if (isApi(req)) return res.status(404).json({ error: 'post not found' });
    return res.status(404).render('404');
  }
  if (!canManagePost(course_id, existing, req.session.user)) return deny(req, res);

  postRepo.deletePost(course_id, post_id);
  if (isApi(req)) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/posts`);
}

module.exports = { newPostForm, createPost, listPosts, getPost, editPostForm, updatePost, deletePost };