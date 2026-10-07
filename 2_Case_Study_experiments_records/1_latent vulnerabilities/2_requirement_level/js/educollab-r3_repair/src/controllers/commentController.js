const courseRepository = require('../repositories/courseRepository');
const postRepository = require('../repositories/postRepository');
const commentRepository = require('../repositories/commentRepository');

const STAFF_ROLES = new Set(['teacher', 'admin', 'assistant', 'senior-assistant']);

function isCourseStaff(membership) {
  return !!(membership && STAFF_ROLES.has(membership.role_in_course));
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

function ensureComment(courseId, postId, commentId) {
  const comment = commentRepository.findById(commentId);
  if (!comment) {
    return null;
  }
  if (String(comment.course_id) !== String(courseId) || String(comment.post_id) !== String(postId)) {
    return null;
  }
  return comment;
}

function createComment(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).send('Post not found');
  }

  const { body } = req.body;
  if (!body) {
    return res.status(400).send('Comment body is required');
  }

  commentRepository.createComment({
    post_id: req.params.post_id,
    course_id: req.params.course_id,
    author_id: req.currentUser.user_id,
    body
  });

  return res.redirect(`/courses/${req.params.course_id}/posts/${req.params.post_id}`);
}

function apiCreateComment(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }

  const { body } = req.body;
  if (!body) {
    return res.status(400).json({ success: false, error: 'body is required' });
  }

  const comment = commentRepository.createComment({
    post_id: req.params.post_id,
    course_id: req.params.course_id,
    author_id: req.currentUser.user_id,
    body
  });

  return res.status(201).json({ success: true, comment });
}

function updateComment(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).send('Post not found');
  }

  const comment = ensureComment(req.params.course_id, req.params.post_id, req.params.comment_id);
  if (!comment) {
    return res.status(404).send('Comment not found');
  }

  const staff = isCourseStaff(req.courseMembership);
  const isAuthor = req.currentUser && String(req.currentUser.user_id) === String(comment.author_id);
  if (!staff && !isAuthor) {
    return res.status(403).send('Forbidden');
  }

  const { body } = req.body;
  if (!body) {
    return res.status(400).send('Comment body is required');
  }

  const updated = staff
    ? commentRepository.updateComment(req.params.comment_id, { body })
    : commentRepository.updateCommentAsAuthor(req.params.comment_id, req.currentUser.user_id, { body });

  if (!updated) {
    return res.status(403).send('Forbidden');
  }
  return res.redirect(`/courses/${req.params.course_id}/posts/${req.params.post_id}`);
}

function apiUpdateComment(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }

  const comment = ensureComment(req.params.course_id, req.params.post_id, req.params.comment_id);
  if (!comment) {
    return res.status(404).json({ success: false, error: 'Comment not found' });
  }

  const staff = isCourseStaff(req.courseMembership);
  const isAuthor = req.currentUser && String(req.currentUser.user_id) === String(comment.author_id);
  if (!staff && !isAuthor) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }

  const { body } = req.body;
  if (!body) {
    return res.status(400).json({ success: false, error: 'body is required' });
  }

  const updated = staff
    ? commentRepository.updateComment(req.params.comment_id, { body })
    : commentRepository.updateCommentAsAuthor(req.params.comment_id, req.currentUser.user_id, { body });

  if (!updated) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }
  return res.json({ success: true, comment: updated });
}

function deleteComment(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).send('Post not found');
  }

  const comment = ensureComment(req.params.course_id, req.params.post_id, req.params.comment_id);
  if (!comment) {
    return res.status(404).send('Comment not found');
  }

  const staff = isCourseStaff(req.courseMembership);
  const isAuthor = req.currentUser && String(req.currentUser.user_id) === String(comment.author_id);
  if (!staff && !isAuthor) {
    return res.status(403).send('Forbidden');
  }

  const deleted = staff
    ? commentRepository.deleteComment(req.params.comment_id)
    : commentRepository.deleteCommentAsAuthor(req.params.comment_id, req.currentUser.user_id);

  if (deleted && deleted.changes === 0) {
    return res.status(403).send('Forbidden');
  }
  return res.redirect(`/courses/${req.params.course_id}/posts/${req.params.post_id}`);
}

function apiDeleteComment(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const post = ensurePost(req.params.course_id, req.params.post_id);
  if (!post) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }

  const comment = ensureComment(req.params.course_id, req.params.post_id, req.params.comment_id);
  if (!comment) {
    return res.status(404).json({ success: false, error: 'Comment not found' });
  }

  const staff = isCourseStaff(req.courseMembership);
  const isAuthor = req.currentUser && String(req.currentUser.user_id) === String(comment.author_id);
  if (!staff && !isAuthor) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }

  const deleted = staff
    ? commentRepository.deleteComment(req.params.comment_id)
    : commentRepository.deleteCommentAsAuthor(req.params.comment_id, req.currentUser.user_id);

  if (deleted && deleted.changes === 0) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }
  return res.json({ success: true });
}

function searchComments(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const keyword = req.query.keyword || '';
  const comments = commentRepository.searchByCourse(req.params.course_id, keyword);
  return res.render('search_comments_results', { course, keyword, comments });
}

function apiSearchComments(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const keyword = req.query.keyword || '';
  const comments = commentRepository.searchByCourse(req.params.course_id, keyword);
  return res.json({ success: true, keyword, comments });
}

module.exports = {
  createComment,
  apiCreateComment,
  updateComment,
  apiUpdateComment,
  deleteComment,
  apiDeleteComment,
  searchComments,
  apiSearchComments
};