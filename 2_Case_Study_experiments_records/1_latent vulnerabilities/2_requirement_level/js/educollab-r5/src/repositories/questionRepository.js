const { db } = require('../db');

function createQuestion({ course_id, created_by, qtype, prompt, options_json, answer_json }) {
  const result = db.prepare(`
    INSERT INTO questions (course_id, created_by, qtype, prompt, options_json, answer_json)
    VALUES (?, ?, ?, ?, ?, ?)
  `).run(course_id, created_by, qtype, prompt, options_json || null, answer_json || null);
  return findById(course_id, result.lastInsertRowid);
}

function listByCourse(courseId) {
  return db.prepare(`
    SELECT q.*, u.username AS creator_username, u.display_name AS creator_display_name
    FROM questions q
    JOIN users u ON u.user_id = q.created_by
    WHERE q.course_id = ?
    ORDER BY q.question_id DESC
  `).all(courseId);
}

function findById(courseId, questionId) {
  return db.prepare(`
    SELECT q.*, u.username AS creator_username, u.display_name AS creator_display_name
    FROM questions q
    JOIN users u ON u.user_id = q.created_by
    WHERE q.course_id = ? AND q.question_id = ?
  `).get(courseId, questionId);
}

function updateQuestion(courseId, questionId, { qtype, prompt, options_json, answer_json }) {
  db.prepare(`
    UPDATE questions
    SET qtype = ?, prompt = ?, options_json = ?, answer_json = ?, updated_at = CURRENT_TIMESTAMP
    WHERE course_id = ? AND question_id = ?
  `).run(qtype, prompt, options_json || null, answer_json || null, courseId, questionId);
  return findById(courseId, questionId);
}

function deleteQuestion(courseId, questionId) {
  return db.prepare(`DELETE FROM questions WHERE course_id = ? AND question_id = ?`).run(courseId, questionId);
}

module.exports = { createQuestion, listByCourse, findById, updateQuestion, deleteQuestion };
