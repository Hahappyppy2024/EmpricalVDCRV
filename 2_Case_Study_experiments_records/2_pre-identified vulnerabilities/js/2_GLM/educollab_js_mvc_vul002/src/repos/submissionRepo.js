const { db, nowIso } = require('../db');

function createSubmission({ assignment_id, course_id, student_id, content_text, attachment_upload_id }) {
  const ts = nowIso();
  const info = db.prepare(
    'INSERT INTO submissions (assignment_id, course_id, student_id, content_text, attachment_upload_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?)'
  ).run(assignment_id, course_id, student_id, content_text, attachment_upload_id || null, ts, ts);
  return getById(course_id, assignment_id, info.lastInsertRowid);
}

function getById(course_id, assignment_id, submission_id) {
  return db.prepare(`
    SELECT s.*, u.username as student_username
    FROM submissions s
    JOIN users u ON u.user_id = s.student_id
    WHERE s.course_id=? AND s.assignment_id=? AND s.submission_id=?
  `).get(course_id, assignment_id, submission_id);
}

function getMySubmission(course_id, assignment_id, student_id) {
  return db.prepare(`
    SELECT s.*
    FROM submissions s
    WHERE s.course_id=? AND s.assignment_id=? AND s.student_id=?
  `).get(course_id, assignment_id, student_id);
}

function updateSubmission(course_id, assignment_id, submission_id, { content_text, attachment_upload_id }) {
  db.prepare(
    'UPDATE submissions SET content_text=?, attachment_upload_id=?, updated_at=? WHERE course_id=? AND assignment_id=? AND submission_id=?'
  ).run(content_text, attachment_upload_id || null, nowIso(), course_id, assignment_id, submission_id);
  return getById(course_id, assignment_id, submission_id);
}

function listMySubmissions(student_id) {
  return db.prepare(`
    SELECT s.*, a.title as assignment_title, c.title as course_title
    FROM submissions s
    JOIN assignments a ON a.assignment_id = s.assignment_id
    JOIN courses c ON c.course_id = s.course_id
    WHERE s.student_id=?
    ORDER BY s.updated_at DESC
  `).all(student_id);
}

function listForAssignment(course_id, assignment_id) {
  return db.prepare(`
    SELECT s.*, u.username as student_username
    FROM submissions s
    JOIN users u ON u.user_id = s.student_id
    WHERE s.course_id=? AND s.assignment_id=?
    ORDER BY s.updated_at DESC
  `).all(course_id, assignment_id);
}

module.exports = {
  createSubmission,
  getById,
  getMySubmission,
  updateSubmission,
  listMySubmissions,
  listForAssignment
};
