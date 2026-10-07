const { db } = require('../db');

function createAssignment({ course_id, created_by, title, description, due_at }) {
  const result = db.prepare(`
    INSERT INTO assignments (course_id, created_by, title, description, due_at)
    VALUES (?, ?, ?, ?, ?)
  `).run(course_id, created_by, title, description || '', due_at || null);
  return findById(course_id, result.lastInsertRowid);
}

function listByCourse(courseId) {
  return db.prepare(`
    SELECT a.*, u.username AS creator_username, u.display_name AS creator_display_name
    FROM assignments a
    JOIN users u ON u.user_id = a.created_by
    WHERE a.course_id = ?
    ORDER BY a.assignment_id DESC
  `).all(courseId);
}

function findById(courseId, assignmentId) {
  return db.prepare(`
    SELECT a.*, u.username AS creator_username, u.display_name AS creator_display_name
    FROM assignments a
    JOIN users u ON u.user_id = a.created_by
    WHERE a.course_id = ? AND a.assignment_id = ?
  `).get(courseId, assignmentId);
}

function updateAssignment(courseId, assignmentId, { title, description, due_at }) {
  db.prepare(`
    UPDATE assignments
    SET title = ?, description = ?, due_at = ?, updated_at = CURRENT_TIMESTAMP
    WHERE course_id = ? AND assignment_id = ?
  `).run(title, description || '', due_at || null, courseId, assignmentId);
  return findById(courseId, assignmentId);
}

function deleteAssignment(courseId, assignmentId) {
  return db.prepare(`
    DELETE FROM assignments WHERE course_id = ? AND assignment_id = ?
  `).run(courseId, assignmentId);
}

module.exports = { createAssignment, listByCourse, findById, updateAssignment, deleteAssignment };
