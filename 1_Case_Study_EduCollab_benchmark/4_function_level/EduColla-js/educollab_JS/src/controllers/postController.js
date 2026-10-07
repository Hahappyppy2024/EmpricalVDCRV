const postRepo = require('../repos/postRepo');
const commentRepo = require('../repos/commentRepo');

function newPostForm(req, res) {
  res.render('posts/new', { course_id: Number(req.params.course_id) });
}

function createPost(req, res) {
  const course_id = Number(req.params.course_id);
  const { title, body } = req.body;
  const me = req.session.user;
  const post = postRepo.createPost({ course_id, author_id: me.user_id, title, body });
  if (req.originalUrl.startsWith('/api')) return res.status(201).json({ post });
  res.redirect(`/courses/${course_id}/posts/${post.post_id}`);
}

function listPosts(req, res) {
  const course_id = Number(req.params.course_id);
  const posts = postRepo.listPosts(course_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ posts });
  res.render('posts/list', { course_id, posts });
}

function getPost(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  const post = postRepo.getById(course_id, post_id);
  if (!post) {
    if (req.originalUrl.startsWith('/api')) return res.status(404).json({ error: 'post not found' });
    return res.status(404).render('404');
  }
  const comments = commentRepo.listComments(course_id, post_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ post, comments });
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
  const post = postRepo.updatePost(course_id, post_id, { title, body });
  if (req.originalUrl.startsWith('/api')) return res.json({ post });
  res.redirect(`/courses/${course_id}/posts/${post_id}`);
}

function deletePost(req, res) {
  const course_id = Number(req.params.course_id);
  const post_id = Number(req.params.post_id);
  postRepo.deletePost(course_id, post_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/posts`);
}

module.exports = { newPostForm, createPost, listPosts, getPost, editPostForm, updatePost, deletePost };
