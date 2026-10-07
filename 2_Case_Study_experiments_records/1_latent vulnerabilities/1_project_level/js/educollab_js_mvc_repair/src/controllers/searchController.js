const postRepo = require('../repos/postRepo');
const commentRepo = require('../repos/commentRepo');

function searchPosts(req, res) {
  const course_id = Number(req.params.course_id);
  const keyword = (req.query.keyword || '').trim();
  const posts = keyword ? postRepo.searchPosts(course_id, keyword) : [];
  if (req.path.startsWith('/api')) return res.json({ keyword, posts });
  res.render('search/posts', { course_id, keyword, posts });
}

function searchComments(req, res) {
  const course_id = Number(req.params.course_id);
  const keyword = (req.query.keyword || '').trim();
  const comments = keyword ? commentRepo.searchComments(course_id, keyword) : [];
  if (req.path.startsWith('/api')) return res.json({ keyword, comments });
  res.render('search/comments', { course_id, keyword, comments });
}

module.exports = { searchPosts, searchComments };
