const commentRepo = require('../repos/commentRepo');
const membershipRepo = require('../repos/membershipRepo');

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

function getCommentForRequest(course_id, post_id, comment_id) {
  const comments = commentRepo.listComments(course_id, post_id) || [];
  return comments.find((comment) => Number(comment.comment_id) === comment_id) || null;
}

function canManageComment(me, course_id, comment) {
  if (!me || !comment) return false;
  if (me.username === 'admin') return true;
  if (Number(comment.author_id) === Number(me.user_id)) return true;

  const membership = membershipRepo.getByUserAndCourse(me.user_id, course_id);
  if (!membership) return false;
  return membership.role_in_course === 'teacher' || membership.role_in_course === 'admin';
}

function updateComment(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const comment_id = Number(req.params.comment_id);
  const { body } = req.body;
  const me = req.session.user;
  const existingComment = getCommentForRequest(course_id, post_id, comment_id);

  if (!existingComment) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'comment not found' });
    return res.status(404).render('404');
  }

  if (!canManageComment(me, course_id, existingComment)) {
    if (req.path.startsWith('/api')) return res.status(403).json({ error: 'Forbidden' });
    return res.status(403).render('403');
  }

  const comment = commentRepo.updateComment(course_id, post_id, comment_id, body);
  if (req.path.startsWith('/api')) return res.json({ comment });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

function deleteComment(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const comment_id = Number(req.params.comment_id);
  const me = req.session.user;
  const existingComment = getCommentForRequest(course_id, post_id, comment_id);

  if (!existingComment) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'comment not found' });
    return res.status(404).render('404');
  }

  if (!canManageComment(me, course_id, existingComment)) {
    if (req.path.startsWith('/api')) return res.status(403).json({ error: 'Forbidden' });
    return res.status(403).render('403');
  }

  commentRepo.deleteComment(course_id, post_id, comment_id);
  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

module.exports = { createComment, listComments, updateComment, deleteComment };