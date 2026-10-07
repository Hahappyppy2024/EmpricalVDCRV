const courseRepository = require('../repositories/courseRepository');
const postRepository = require('../repositories/postRepository');
const commentRepository = require('../repositories/commentRepository');

function showNewPost(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('post_new', { course, error: null });
}

function createPost(req, res) {
  const courseId = Number(req.params.course_id);
  const { title, body } = req.body;
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  if (!title) return res.status(400).render('post_new', { course, error: 'Title is required.' });
  const post = postRepository.createPost({
    course_id: courseId,
    author_id: req.currentUser.user_id,
    title,
    body
  });
  return res.redirect(`/courses/${courseId}/posts/${post.post_id}`);
}

function apiCreatePost(req, res) {
  const courseId = Number(req.params.course_id);
  const { title, body } = req.body;
  if (!title) return res.status(400).json({ success: false, error: 'title is required' });
  const post = postRepository.createPost({
    course_id: courseId,
    author_id: req.currentUser.user_id,
    title,
    body
  });
  return res.status(201).json({ success: true, post });
}

function listPosts(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('posts_list', { course, posts: postRepository.listByCourse(course.course_id) });
}

function apiListPosts(req, res) {
  res.json({ success: true, posts: postRepository.listByCourse(Number(req.params.course_id)) });
}

function getPost(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const course = courseRepository.findById(courseId);
  const post = postRepository.findById(courseId, postId);
  if (!course || !post) return res.status(404).send('Post not found');
  res.render('post_show', {
    course,
    post,
    comments: commentRepository.listByPost(courseId, postId)
  });
}

function apiGetPost(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const post = postRepository.findById(courseId, postId);
  if (!post) return res.status(404).json({ success: false, error: 'Post not found' });
  res.json({ success: true, post, comments: commentRepository.listByPost(courseId, postId) });
}

function showEditPost(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const course = courseRepository.findById(courseId);
  const post = postRepository.findById(courseId, postId);
  if (!course || !post) return res.status(404).send('Post not found');
  res.render('post_edit', { course, post, error: null });
}

function updatePost(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const { title, body } = req.body;
  const existing = postRepository.findById(courseId, postId);
  if (!existing) return res.status(404).send('Post not found');
  if (!title) {
    return res.status(400).render('post_edit', {
      course: courseRepository.findById(courseId),
      post: existing,
      error: 'Title is required.'
    });
  }
  postRepository.updatePost(courseId, postId, { title, body });
  return res.redirect(`/courses/${courseId}/posts/${postId}`);
}

function apiUpdatePost(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  const { title, body } = req.body;
  if (!postRepository.findById(courseId, postId)) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }
  if (!title) return res.status(400).json({ success: false, error: 'title is required' });
  const post = postRepository.updatePost(courseId, postId, { title, body });
  return res.json({ success: true, post });
}

function deletePost(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  if (!postRepository.findById(courseId, postId)) return res.status(404).send('Post not found');
  postRepository.deletePost(courseId, postId);
  return res.redirect(`/courses/${courseId}/posts`);
}

function apiDeletePost(req, res) {
  const courseId = Number(req.params.course_id);
  const postId = Number(req.params.post_id);
  if (!postRepository.findById(courseId, postId)) {
    return res.status(404).json({ success: false, error: 'Post not found' });
  }
  postRepository.deletePost(courseId, postId);
  return res.json({ success: true });
}

function searchPosts(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  const keyword = req.query.keyword || '';
  res.render('search_posts_results', {
    course,
    keyword,
    posts: postRepository.searchPosts(courseId, keyword)
  });
}

function apiSearchPosts(req, res) {
  const courseId = Number(req.params.course_id);
  const keyword = req.query.keyword || '';
  res.json({ success: true, keyword, posts: postRepository.searchPosts(courseId, keyword) });
}

module.exports = {
  showNewPost, createPost, apiCreatePost,
  listPosts, apiListPosts,
  getPost, apiGetPost,
  showEditPost, updatePost, apiUpdatePost,
  deletePost, apiDeletePost,
  searchPosts, apiSearchPosts
};
