const { db, nowIso } = require('../db');

function createComment({ course_id, post_id, author_id, body }) {
  const ts = nowIso();
  const info = db.prepare(
    'INSERT INTO comments (post_id, course_id, author_id, body, created_at, updated_at) VALUES (?,?,?,?,?,?)'
  ).run(post_id, course_id, author_id, body, ts, ts);
  return getById(course_id, post_id, info.lastInsertRowid);
}

function listComments(course_id, post_id) {
  return db.prepare(`
    SELECT c.*, u.username as author_username
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    WHERE c.course_id=? AND c.post_id=?
    ORDER BY c.comment_id ASC
  `).all(course_id, post_id);
}

function getById(course_id, post_id, comment_id) {
  return db.prepare(`
    SELECT c.*, u.username as author_username
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    WHERE c.course_id=? AND c.post_id=? AND c.comment_id=?
  `).get(course_id, post_id, comment_id);
}

function updateComment(course_id, post_id, comment_id, body) {
  db.prepare('UPDATE comments SET body=?, updated_at=? WHERE course_id=? AND post_id=? AND comment_id=?')
    .run(body, nowIso(), course_id, post_id, comment_id);
  return getById(course_id, post_id, comment_id);
}

function deleteComment(course_id, post_id, comment_id) {
  db.prepare('DELETE FROM comments WHERE course_id=? AND post_id=? AND comment_id=?')
    .run(course_id, post_id, comment_id);
}

function searchComments(course_id, keyword) {
  const k = `%${keyword}%`;
  return db.prepare(`
    SELECT c.*, u.username as author_username
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    WHERE c.course_id=? AND c.body LIKE ?
    ORDER BY c.comment_id DESC
  `).all(course_id, k);
}

module.exports = { createComment, listComments, getById, updateComment, deleteComment, searchComments };
