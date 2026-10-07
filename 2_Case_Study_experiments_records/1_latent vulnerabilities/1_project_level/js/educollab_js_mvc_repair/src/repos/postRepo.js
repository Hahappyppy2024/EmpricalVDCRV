const { db, nowIso } = require('../db');

function createPost({ course_id, author_id, title, body }) {
  const ts = nowIso();
  const info = db
    .prepare('INSERT INTO posts (course_id, author_id, title, body, created_at, updated_at) VALUES (?,?,?,?,?,?)')
    .run(course_id, author_id, title, body, ts, ts);
  return getById(course_id, info.lastInsertRowid);
}

function listPosts(course_id) {
  return db
    .prepare(
      `
    SELECT p.*, u.username as author_username
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.course_id=?
    ORDER BY p.post_id DESC
  `
    )
    .all(course_id);
}

function getById(course_id, post_id) {
  return db
    .prepare(
      `
    SELECT p.*, u.username as author_username
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.course_id=? AND p.post_id=?
  `
    )
    .get(course_id, post_id);
}

function updatePost(course_id, post_id, { title, body }) {
  db.prepare('UPDATE posts SET title=?, body=?, updated_at=? WHERE course_id=? AND post_id=?').run(
    title,
    body,
    nowIso(),
    course_id,
    post_id
  );
  return getById(course_id, post_id);
}

function updatePostAsAuthor(course_id, post_id, author_id, { title, body }) {
  const info = db
    .prepare('UPDATE posts SET title=?, body=?, updated_at=? WHERE course_id=? AND post_id=? AND author_id=?')
    .run(title, body, nowIso(), course_id, post_id, author_id);
  if (!info || info.changes === 0) return null;
  return getById(course_id, post_id);
}

function deletePost(course_id, post_id) {
  db.prepare('DELETE FROM posts WHERE course_id=? AND post_id=?').run(course_id, post_id);
}

function deletePostAsAuthor(course_id, post_id, author_id) {
  const info = db
    .prepare('DELETE FROM posts WHERE course_id=? AND post_id=? AND author_id=?')
    .run(course_id, post_id, author_id);
  return !!(info && info.changes > 0);
}

function searchPosts(course_id, keyword) {
  const k = `%${keyword}%`;
  return db
    .prepare(
      `
    SELECT p.*, u.username as author_username
    FROM posts p
    JOIN users u ON u.user_id = p.author_id
    WHERE p.course_id=? AND (p.title LIKE ? OR p.body LIKE ?)
    ORDER BY p.post_id DESC
  `
    )
    .all(course_id, k, k);
}

module.exports = {
  createPost,
  listPosts,
  getById,
  updatePost,
  updatePostAsAuthor,
  deletePost,
  deletePostAsAuthor,
  searchPosts
};