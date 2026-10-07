const { db, nowIso } = require('../db');

function startAttempt({ quiz_id, course_id, student_id }) {
  const info = db.prepare(
    'INSERT INTO quiz_attempts (quiz_id, course_id, student_id, started_at, submitted_at) VALUES (?,?,?,?,NULL)'
  ).run(quiz_id, course_id, student_id, nowIso());
  return getAttempt(info.lastInsertRowid);
}

function getAttempt(attempt_id) {
  return db.prepare('SELECT * FROM quiz_attempts WHERE attempt_id=?').get(attempt_id);
}

function listMyAttempts(student_id) {
  return db.prepare(`
    SELECT a.*, qz.title as quiz_title, c.title as course_title
    FROM quiz_attempts a
    JOIN quizzes qz ON qz.quiz_id = a.quiz_id
    JOIN courses c ON c.course_id = a.course_id
    WHERE a.student_id=?
    ORDER BY a.attempt_id DESC
  `).all(student_id);
}

function upsertAnswer({ attempt_id, question_id, answer_json }) {
  db.prepare(
    'INSERT INTO quiz_answers (attempt_id, question_id, answer_json, created_at) VALUES (?,?,?,?)\n     ON CONFLICT(attempt_id, question_id) DO UPDATE SET answer_json=excluded.answer_json'
  ).run(attempt_id, question_id, answer_json, nowIso());

  return db.prepare('SELECT * FROM quiz_answers WHERE attempt_id=? AND question_id=?').get(attempt_id, question_id);
}

function submitAttempt(attempt_id) {
  db.prepare('UPDATE quiz_attempts SET submitted_at=? WHERE attempt_id=?').run(nowIso(), attempt_id);
  return getAttempt(attempt_id);
}

function listAnswers(attempt_id) {
  return db.prepare('SELECT * FROM quiz_answers WHERE attempt_id=?').all(attempt_id);
}

module.exports = { startAttempt, getAttempt, listMyAttempts, upsertAnswer, submitAttempt, listAnswers };
