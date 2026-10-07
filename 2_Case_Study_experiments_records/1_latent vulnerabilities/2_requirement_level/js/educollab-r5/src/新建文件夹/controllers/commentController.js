const courseRepository = require('../repositories/courseRepository');
const postRepository = require('../repositories/postRepository');
const commentRepository = require('../repositories/commentRepository');

function createComment(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const { body } = req.body;
  const post = postRepository.findById(courseId, postId);
  if (!post) return res.status(404).send('Post not found');
  if (!body) return res.status(400).send('body is required');
  commentRepository.createComment({
    post_id: postId,
    course_id: courseId,
    author_id: req.currentUser.user_id,
    body
  });
  return res.redirect(`/courses/${courseId}/posts/${postId}`);
}

function apiCreateComment(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const { body } = req.body;
  if (!postRepository.findById(courseId, postId)) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }
  if (!body) return res.status(400).json({ success: false, error: 'body is required' });
  const comment = commentRepository.createComment({
    post_id: postId,
    course_id: courseId,
    author_id: req.currentUser.user_id,
    body
  });
  return res.status(201).json({ success: true, comment });
}

function listComments(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const course = courseRepository.findById(courseId);
  const post = postRepository.findById(courseId, postId);
  if (!course || !post) return res.status(404).send('Post not found');
  res.render('comments_list', {
    course,
    post,
    comments: commentRepository.listByPost(courseId, postId)
  });
}

function apiListComments(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  if (!postRepository.findById(courseId, postId)) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }
  res.json({ success: true, comments: commentRepository.listByPost(courseId, postId) });
}

function updateComment(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const commentId = Number(req.params.comment_id);
  const { body } = req.body;
  if (!body) return res.status(400).send('body is required');
  const comment = commentRepository.findById(courseId, postId, commentId);
  if (!comment) return res.status(404).send('Comment not found');
  commentRepository.updateComment(courseId, postId, commentId, { body });
  return res.redirect(`/courses/${courseId}/posts/${postId}`);
}

function apiUpdateComment(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const commentId = Number(req.params.comment_id);
  const { body } = req.body;
  if (!body) return res.status(400).json({ success: false, error: 'body is required' });
  if (!commentRepository.findById(courseId, postId, commentId)) {
    return res.status(404).json({ success: false, error: 'Comment not found' });
  }
  const comment = commentRepository.updateComment(courseId, postId, commentId, { body });
  return res.json({ success: true, comment });
}

function deleteComment(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const commentId = Number(req.params.comment_id);
  if (!commentRepository.findById(courseId, postId, commentId)) return res.status(404).send('Comment not found');
  commentRepository.deleteComment(courseId, postId, commentId);
  return res.redirect(`/courses/${courseId}/posts/${postId}`);
}

function apiDeleteComment(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const commentId = Number(req.params.comment_id);
  if (!commentRepository.findById(courseId, postId, commentId)) {
    return res.status(404).json({ success: false, error: 'Comment not found' });
  }
  commentRepository.deleteComment(courseId, postId, commentId);
  return res.json({ success: true });
}

function searchComments(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  const keyword = req.query.keyword || '';
  res.render('search_comments_results', {
    course,
    keyword,
    comments: commentRepository.searchComments(courseId, keyword)
  });
}

function apiSearchComments(req, res) {
  const courseId = Number(req.params.course_id);
  const keyword = req.query.keyword || '';
  res.json({ success: true, keyword, comments: commentRepository.searchComments(courseId, keyword) });
}

module.exports = {
  createComment, apiCreateComment,
  listComments, apiListComments,
  updateComment, apiUpdateComment,
  deleteComment, apiDeleteComment,
  searchComments, apiSearchComments
};
