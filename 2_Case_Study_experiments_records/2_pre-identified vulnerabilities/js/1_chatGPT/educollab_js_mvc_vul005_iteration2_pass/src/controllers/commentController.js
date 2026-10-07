const commentRepo = require('../repos/commentRepo');

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
  const comment = commentRepo.updateComment(course_id, post_id, comment_id, body);
  if (req.path.startsWith('/api')) return res.json({ comment });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

function deleteComment(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const comment_id = Number(req.params.comment_id);
  commentRepo.deleteComment(course_id, post_id, comment_id);
  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

module.exports = { createComment, listComments, updateComment, deleteComment };
