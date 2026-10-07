const { db } = require('../db');

function createComment({ post_id, course_id, author_id, body }) {
  const result = db.prepare(`
    INSERT INTO comments (post_id, course_id, author_id, body)
    VALUES (?, ?, ?, ?)
  `).run(post_id, course_id, author_id, body);
  return findById(course_id, post_id, result.lastInsertRowid);
}

function listByPost(courseId, postId) {
  return db.prepare(`
    SELECT c.*, u.username AS author_username, u.display_name AS author_display_name
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    WHERE c.course_id = ? AND c.post_id = ?
    ORDER BY c.comment_id ASC
  `).all(courseId, postId);
}

function findById(courseId, postId, commentId) {
  return db.prepare(`
    SELECT c.*, u.username AS author_username, u.display_name AS author_display_name
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    WHERE c.course_id = ? AND c.post_id = ? AND c.comment_id = ?
  `).get(courseId, postId, commentId);
}

function updateComment(courseId, postId, commentId, { body }) {
  db.prepare(`
    UPDATE comments
    SET body = ?, updated_at = CURRENT_TIMESTAMP
    WHERE course_id = ? AND post_id = ? AND comment_id = ?
  `).run(body, courseId, postId, commentId);
  return findById(courseId, postId, commentId);
}

function deleteComment(courseId, postId, commentId) {
  return db.prepare(`
    DELETE FROM comments
    WHERE course_id = ? AND post_id = ? AND comment_id = ?
  `).run(courseId, postId, commentId);
}

function searchComments(courseId, keyword) {
  const like = `%${keyword || ''}%`;
  return db.prepare(`
    SELECT c.*, u.username AS author_username, u.display_name AS author_display_name, p.title AS post_title
    FROM comments c
    JOIN users u ON u.user_id = c.author_id
    JOIN posts p ON p.post_id = c.post_id
    WHERE c.course_id = ? AND c.body LIKE ?
    ORDER BY c.comment_id DESC
  `).all(courseId, like);
}

module.exports = { createComment, listByPost, findById, updateComment, deleteComment, searchComments };
