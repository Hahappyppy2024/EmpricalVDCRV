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

function notFound(req, res) {
  if (isApi(req)) return res.status(404).json({ error: 'comment not found' });
  return res.status(404).render('404');
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

function canManageComment(course_id, comment, me) {
  if (!comment || !me) return false;
  if (Number(comment.author_id) === Number(me.user_id)) return true;
  if (me.username === 'admin') return true;
  const membership = getMembershipForUser(course_id, me.user_id);
  const role = membership && membership.role_in_course;
  return STAFF_ROLES.has(role);
}

function createComment(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const { body } = req.body;
  const me = req.session.user;
  const comment = commentRepo.createComment({ course_id, post_id, author_id: me.user_id, body });
  if (req.path.startsWith('/api')) return res.status(201).json({ comment });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

function listComments(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const comments = commentRepo.listComments(course_id, post_id);
  return res.json({ comments });
}

function updateComment(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const comment_id = Number(req.params.comment_id);
  const { body } = req.body;
  const existing = commentRepo.getById(course_id, post_id, comment_id);
  if (!existing) return notFound(req, res);
  if (!canManageComment(course_id, existing, req.session.user)) return deny(req, res);

  const comment = commentRepo.updateComment(course_id, post_id, comment_id, body);
  if (req.path.startsWith('/api')) return res.json({ comment });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

function deleteComment(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const comment_id = Number(req.params.comment_id);
  const existing = commentRepo.getById(course_id, post_id, comment_id);
  if (!existing) return notFound(req, res);
  if (!canManageComment(course_id, existing, req.session.user)) return deny(req, res);

  commentRepo.deleteComment(course_id, post_id, comment_id);
  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

module.exports = { createComment, listComments, updateComment, deleteComment };