const { db } = require('../db');

function createPost({ course_id, author_id, title, body }) {
  const result = db.prepare(`
    INSERT INTO posts (course_id, author_id, title, body)
    VALUES (?, ?, ?, ?)
  `).run(course_id, author_id, title, body || '');
  return findById(result.lastInsertRowid);
}

function listByCourse(courseId) {
  return db.prepare(`
    SELECT
      p.post_id,
      p.course_id,
      p.author_id,
      p.title,
      p.body,
      p.created_at,
      p.updated_at,
      u.username AS author_username,
      u.display_name AS author_display_name
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.course_id = ?
    ORDER BY p.post_id DESC
  `).all(courseId);
}

function findById(postId) {
  return db.prepare(`
    SELECT
      p.post_id,
      p.course_id,
      p.author_id,
      p.title,
      p.body,
      p.created_at,
      p.updated_at,
      u.username AS author_username,
      u.display_name AS author_display_name
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.post_id = ?
  `).get(postId);
}

function updatePost(postId, { title, body }) {
  db.prepare(`
    UPDATE posts
    SET title = ?, body = ?, updated_at = CURRENT_TIMESTAMP
    WHERE post_id = ?
  `).run(title, body || '', postId);
  return findById(postId);
}

function deletePost(postId) {
  db.prepare(`DELETE FROM comments WHERE post_id = ?`).run(postId);
  return db.prepare(`DELETE FROM posts WHERE post_id = ?`).run(postId);
}

function searchByCourse(courseId, keyword) {
  const pattern = `%${keyword || ''}%`;
  return db.prepare(`
    SELECT
      p.post_id,
      p.course_id,
      p.author_id,
      p.title,
      p.body,
      p.created_at,
      p.updated_at,
      u.username AS author_username,
      u.display_name AS author_display_name
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.course_id = ? AND (p.title LIKE ? OR p.body LIKE ?)
    ORDER BY p.post_id DESC
  `).all(courseId, pattern, pattern);
}

module.exports = {
  createPost,
  listByCourse,
  findById,
  updatePost,
  deletePost,
  searchByCourse
};
