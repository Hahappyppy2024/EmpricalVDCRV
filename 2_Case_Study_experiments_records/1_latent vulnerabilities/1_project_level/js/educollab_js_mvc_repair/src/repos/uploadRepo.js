const { db, nowIso } = require('../db');

function createUpload({ course_id, uploaded_by, original_name, storage_path }) {
  const info = db.prepare(
    'INSERT INTO uploads (course_id, uploaded_by, original_name, storage_path, created_at) VALUES (?,?,?,?,?)'
  ).run(course_id, uploaded_by, original_name, storage_path, nowIso());
  return getById(course_id, info.lastInsertRowid);
}

function listUploads(course_id) {
  return db.prepare(`
    SELECT up.*, u.username as uploaded_by_username
    FROM uploads up
    JOIN users u ON u.user_id = up.uploaded_by
    WHERE up.course_id=?
    ORDER BY up.upload_id DESC
  `).all(course_id);
}

function getById(course_id, upload_id) {
  return db.prepare('SELECT * FROM uploads WHERE course_id=? AND upload_id=?').get(course_id, upload_id);
}

function deleteUpload(course_id, upload_id) {
  db.prepare('DELETE FROM uploads WHERE course_id=? AND upload_id=?').run(course_id, upload_id);
}

module.exports = { createUpload, listUploads, getById, deleteUpload };
