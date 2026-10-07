const { db } = require('../db');

function createPost({ course_id, author_id, title, body }) {
  const result = db.prepare(`
    INSERT INTO posts (course_id, author_id, title, body)
    VALUES (?, ?, ?, ?)
  `).run(course_id, author_id, title, body || '');
  return findById(course_id, result.lastInsertRowid);
}

function listByCourse(courseId) {
  return db.prepare(`
    SELECT p.*, u.username AS author_username, u.display_name AS author_display_name
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.course_id = ?
    ORDER BY p.post_id DESC
  `).all(courseId);
}

function findById(courseId, postId) {
  return db.prepare(`
    SELECT p.*, u.username AS author_username, u.display_name AS author_display_name
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.course_id = ? AND p.post_id = ?
  `).get(courseId, postId);
}

function updatePost(courseId, postId, { title, body }) {
  db.prepare(`
    UPDATE posts
    SET title = ?, body = ?, updated_at = CURRENT_TIMESTAMP
    WHERE course_id = ? AND post_id = ?
  `).run(title, body || '', courseId, postId);
  return findById(courseId, postId);
}

function deletePost(courseId, postId) {
  return db.prepare(`
    DELETE FROM posts WHERE course_id = ? AND post_id = ?
  `).run(courseId, postId);
}

function searchPosts(courseId, keyword) {
  const like = `%${keyword || ''}%`;
  return db.prepare(`
    SELECT p.*, u.username AS author_username, u.display_name AS author_display_name
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.course_id = ? AND (p.title LIKE ? OR p.body LIKE ?)
    ORDER BY p.post_id DESC
  `).all(courseId, like, like);
}

module.exports = { createPost, listByCourse, findById, updatePost, deletePost, searchPosts };
