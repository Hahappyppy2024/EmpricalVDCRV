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

  const me = req.session.user;
  const existing = commentRepo.getById(course_id, post_id, comment_id);
  if (!existing) return deny(req, res, 404, 'comment not found');

  const staff = isCourseStaff(req);
  if (!staff && Number(existing.author_id) !== Number(me.user_id)) {
    return deny(req, res, 403, 'Forbidden');
  }

  const comment = staff
    ? commentRepo.updateComment(course_id, post_id, comment_id, body)
    : commentRepo.updateCommentAsAuthor(course_id, post_id, comment_id, me.user_id, body);

  if (!comment) return deny(req, res, 404, 'comment not found');

  if (req.path.startsWith('/api')) return res.json({ comment });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

function deleteComment(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const comment_id = Number(req.params.comment_id);

  const me = req.session.user;
  const existing = commentRepo.getById(course_id, post_id, comment_id);
  if (!existing) return deny(req, res, 404, 'comment not found');

  const staff = isCourseStaff(req);
  if (!staff && Number(existing.author_id) !== Number(me.user_id)) {
    return deny(req, res, 403, 'Forbidden');
  }

  if (staff) commentRepo.deleteComment(course_id, post_id, comment_id);
  else commentRepo.deleteCommentAsAuthor(course_id, post_id, comment_id, me.user_id);

  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

module.exports = { createComment, listComments, updateComment, deleteComment };