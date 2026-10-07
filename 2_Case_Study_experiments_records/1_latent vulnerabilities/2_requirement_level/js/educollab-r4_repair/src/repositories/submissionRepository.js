const { db } = require('../db');

function createSubmission({ assignment_id, course_id, student_id, content_text, attachment_upload_id }) {
  const result = db.prepare(`
    INSERT INTO submissions (assignment_id, course_id, student_id, content_text, attachment_upload_id)
    VALUES (?, ?, ?, ?, ?)
  `).run(assignment_id, course_id, student_id, content_text || '', attachment_upload_id || null);
  return findById(course_id, assignment_id, result.lastInsertRowid);
}

function findById(courseId, assignmentId, submissionId) {
  return db.prepare(`
    SELECT s.*, u.username AS student_username, u.display_name AS student_display_name,
           up.original_name AS attachment_original_name
    FROM submissions s
    JOIN users u ON u.user_id = s.student_id
    LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id AND up.course_id = s.course_id
    WHERE s.course_id = ? AND s.assignment_id = ? AND s.submission_id = ?
  `).get(courseId, assignmentId, submissionId);
}

function listByAssignment(courseId, assignmentId) {
  return db.prepare(`
    SELECT s.*, u.username AS student_username, u.display_name AS student_display_name,
           up.original_name AS attachment_original_name
    FROM submissions s
    JOIN users u ON u.user_id = s.student_id
    LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id AND up.course_id = s.course_id
    WHERE s.course_id = ? AND s.assignment_id = ?
    ORDER BY s.submission_id DESC
  `).all(courseId, assignmentId);
}

function listByStudent(studentId) {
  return db.prepare(`
    SELECT s.*, a.title AS assignment_title, c.title AS course_title,
           up.original_name AS attachment_original_name
    FROM submissions s
    JOIN assignments a ON a.assignment_id = s.assignment_id
    JOIN courses c ON c.course_id = s.course_id
    LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id AND up.course_id = s.course_id
    WHERE s.student_id = ?
    ORDER BY s.submission_id DESC
  `).all(studentId);
}

function updateSubmission(courseId, assignmentId, submissionId, { content_text, attachment_upload_id }) {
  db.prepare(`
    UPDATE submissions
    SET content_text = ?, attachment_upload_id = ?, updated_at = CURRENT_TIMESTAMP
    WHERE course_id = ? AND assignment_id = ? AND submission_id = ?
  `).run(content_text || '', attachment_upload_id || null, courseId, assignmentId, submissionId);
  return findById(courseId, assignmentId, submissionId);
}

module.exports = { createSubmission, findById, listByAssignment, listByStudent, updateSubmission };