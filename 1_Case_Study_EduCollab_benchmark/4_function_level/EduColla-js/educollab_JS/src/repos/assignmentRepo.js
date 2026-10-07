const { db, nowIso } = require('../db');

function createAssignment({ course_id, created_by, title, description, due_at }) {
  const ts = nowIso();
  const info = db.prepare(
    'INSERT INTO assignments (course_id, created_by, title, description, due_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?)'
  ).run(course_id, created_by, title, description, due_at, ts, ts);
  return getById(course_id, info.lastInsertRowid);
}

function listAssignments(course_id) {
  return db.prepare(`
    SELECT a.*, u.username as created_by_username
    FROM assignments a
    JOIN users u ON u.user_id = a.created_by
    WHERE a.course_id=?
    ORDER BY a.assignment_id DESC
  `).all(course_id);
}

function getById(course_id, assignment_id) {
  return db.prepare(`
    SELECT a.*, u.username as created_by_username
    FROM assignments a
    JOIN users u ON u.user_id = a.created_by
    WHERE a.course_id=? AND a.assignment_id=?
  `).get(course_id, assignment_id);
}

function updateAssignment(course_id, assignment_id, { title, description, due_at }) {
  db.prepare('UPDATE assignments SET title=?, description=?, due_at=?, updated_at=? WHERE course_id=? AND assignment_id=?')
    .run(title, description, due_at, nowIso(), course_id, assignment_id);
  return getById(course_id, assignment_id);
}

function deleteAssignment(course_id, assignment_id) {
  db.prepare('DELETE FROM assignments WHERE course_id=? AND assignment_id=?').run(course_id, assignment_id);
}

module.exports = { createAssignment, listAssignments, getById, updateAssignment, deleteAssignment };
