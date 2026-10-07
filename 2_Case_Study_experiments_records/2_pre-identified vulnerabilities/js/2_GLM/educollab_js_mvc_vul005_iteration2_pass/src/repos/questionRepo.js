const { db, nowIso } = require('../db');

function createQuestion({ course_id, created_by, qtype, prompt, options_json, answer_json }) {
  const ts = nowIso();
  const info = db.prepare(
    'INSERT INTO questions (course_id, created_by, qtype, prompt, options_json, answer_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)'
  ).run(course_id, created_by, qtype, prompt, options_json || null, answer_json || null, ts, ts);
  return getById(course_id, info.lastInsertRowid);
}

function listQuestions(course_id) {
  return db.prepare(`
    SELECT q.*, u.username as created_by_username
    FROM questions q
    JOIN users u ON u.user_id = q.created_by
    WHERE q.course_id=?
    ORDER BY q.question_id DESC
  `).all(course_id);
}

function getById(course_id, question_id) {
  return db.prepare('SELECT * FROM questions WHERE course_id=? AND question_id=?').get(course_id, question_id);
}

function updateQuestion(course_id, question_id, { qtype, prompt, options_json, answer_json }) {
  db.prepare(
    'UPDATE questions SET qtype=?, prompt=?, options_json=?, answer_json=?, updated_at=? WHERE course_id=? AND question_id=?'
  ).run(qtype, prompt, options_json || null, answer_json || null, nowIso(), course_id, question_id);
  return getById(course_id, question_id);
}

function deleteQuestion(course_id, question_id) {
  db.prepare('DELETE FROM questions WHERE course_id=? AND question_id=?').run(course_id, question_id);
}

module.exports = { createQuestion, listQuestions, getById, updateQuestion, deleteQuestion };
