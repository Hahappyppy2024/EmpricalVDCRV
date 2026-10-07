const { db } = require('../db');

function createComment({ post_id, course_id, author_id, body }) {
  const result = db.prepare(`
    INSERT INTO comments (post_id, course_id, author_id, body)
    VALUES (?, ?, ?, ?)
  `).run(post_id, course_id, author_id, body);
  return findById(result.lastInsertRowid);
}

function listByPost(postId) {
  return db.prepare(`
    SELECT
      c.comment_id,
      c.post_id,
      c.course_id,
      c.author_id,
      c.body,
      c.created_at,
      c.updated_at,
      u.username AS author_username,
      u.display_name AS author_display_name
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    WHERE c.post_id = ?
    ORDER BY c.comment_id ASC
  `).all(postId);
}

function findById(commentId) {
  return db.prepare(`
    SELECT
      c.comment_id,
      c.post_id,
      c.course_id,
      c.author_id,
      c.body,
      c.created_at,
      c.updated_at,
      u.username AS author_username,
      u.display_name AS author_display_name
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    WHERE c.comment_id = ?
  `).get(commentId);
}

function updateComment(commentId, { body }) {
  db.prepare(`
    UPDATE comments
    SET body = ?, updated_at = CURRENT_TIMESTAMP
    WHERE comment_id = ?
  `).run(body, commentId);
  return findById(commentId);
}

function updateCommentAsAuthor(commentId, authorId, { body }) {
  const info = db.prepare(`
    UPDATE comments
    SET body = ?, updated_at = CURRENT_TIMESTAMP
    WHERE comment_id = ? AND author_id = ?
  `).run(body, commentId, authorId);

  if (info.changes === 0) {
    return null;
  }
  return findById(commentId);
}

function deleteComment(commentId) {
  return db.prepare(`DELETE FROM comments WHERE comment_id = ?`).run(commentId);
}

function deleteCommentAsAuthor(commentId, authorId) {
  return db.prepare(`DELETE FROM comments WHERE comment_id = ? AND author_id = ?`).run(commentId, authorId);
}

function searchByCourse(courseId, keyword) {
  const pattern = `%${keyword || ''}%`;
  return db.prepare(`
    SELECT
      c.comment_id,
      c.post_id,
      c.course_id,
      c.author_id,
      c.body,
      c.created_at,
      c.updated_at,
      u.username AS author_username,
      u.display_name AS author_display_name,
      p.title AS post_title
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    JOIN posts p ON p.post_id = c.post_id
    WHERE c.course_id = ? AND c.body LIKE ?
    ORDER BY c.comment_id DESC
  `).all(courseId, pattern);
}

module.exports = {
  createComment,
  listByPost,
  findById,
  updateComment,
  updateCommentAsAuthor,
  deleteComment,
  deleteCommentAsAuthor,
  searchByCourse
};