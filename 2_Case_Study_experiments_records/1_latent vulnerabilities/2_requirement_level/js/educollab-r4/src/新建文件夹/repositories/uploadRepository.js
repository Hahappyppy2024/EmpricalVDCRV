const { db } = require('../db');

function createUpload({ course_id, uploaded_by, original_name, storage_path }) {
  const result = db.prepare(`
    INSERT INTO uploads (course_id, uploaded_by, original_name, storage_path)
    VALUES (?, ?, ?, ?)
  `).run(course_id, uploaded_by, original_name, storage_path);
  return findById(course_id, result.lastInsertRowid);
}

function listByCourse(courseId) {
  return db.prepare(`
    SELECT u.*, usr.username AS uploaded_by_username, usr.display_name AS uploaded_by_display_name
    FROM uploads u
    JOIN users usr ON usr.user_id = u.uploaded_by
    WHERE u.course_id = ?
    ORDER BY u.upload_id DESC
  `).all(courseId);
}

function findById(courseId, uploadId) {
  return db.prepare(`
    SELECT u.*, usr.username AS uploaded_by_username, usr.display_name AS uploaded_by_display_name
    FROM uploads u
    JOIN users usr ON usr.user_id = u.uploaded_by
    WHERE u.course_id = ? AND u.upload_id = ?
  `).get(courseId, uploadId);
}

function deleteUpload(courseId, uploadId) {
  return db.prepare(`
    DELETE FROM uploads WHERE course_id = ? AND upload_id = ?
  `).run(courseId, uploadId);
}

module.exports = { createUpload, listByCourse, findById, deleteUpload };
