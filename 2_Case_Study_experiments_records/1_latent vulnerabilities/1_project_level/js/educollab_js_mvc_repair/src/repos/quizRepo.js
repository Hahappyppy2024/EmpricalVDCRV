const { db, nowIso } = require('../db');

function createQuiz({ course_id, created_by, title, description, open_at, due_at }) {
  const ts = nowIso();
  const info = db
    .prepare(
      'INSERT INTO quizzes (course_id, created_by, title, description, open_at, due_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)'
    )
    .run(course_id, created_by, title, description, open_at, due_at, ts, ts);
  return getById(course_id, info.lastInsertRowid);
}

function listQuizzes(course_id) {
  return db
    .prepare(
      `
    SELECT qz.*, u.username as created_by_username
    FROM quizzes qz
    JOIN users u ON u.user_id = qz.created_by
    WHERE qz.course_id=?
    ORDER BY qz.quiz_id DESC
  `
    )
    .all(course_id);
}

function getById(course_id, quiz_id) {
  return db.prepare('SELECT * FROM quizzes WHERE course_id=? AND quiz_id=?').get(course_id, quiz_id);
}

function updateQuiz(course_id, quiz_id, { title, description, open_at, due_at }) {
  db.prepare('UPDATE quizzes SET title=?, description=?, open_at=?, due_at=?, updated_at=? WHERE course_id=? AND quiz_id=?')
    .run(title, description, open_at, due_at, nowIso(), course_id, quiz_id);
  return getById(course_id, quiz_id);
}

function deleteQuiz(course_id, quiz_id) {
  db.prepare('DELETE FROM quizzes WHERE course_id=? AND quiz_id=?').run(course_id, quiz_id);
}

function addQuizQuestion({ quiz_id, question_id, points, position }) {
  db.prepare('INSERT OR REPLACE INTO quiz_questions (quiz_id, question_id, points, position) VALUES (?,?,?,?)').run(
    quiz_id,
    question_id,
    points,
    position
  );
  return getQuizQuestions(quiz_id);
}

function removeQuizQuestion(quiz_id, question_id) {
  db.prepare('DELETE FROM quiz_questions WHERE quiz_id=? AND question_id=?').run(quiz_id, question_id);
}

function getQuizQuestions(quiz_id) {
  return db
    .prepare(
      `
    SELECT qq.*, q.prompt, q.qtype
    FROM quiz_questions qq
    JOIN questions q ON q.question_id = qq.question_id
    WHERE qq.quiz_id=?
    ORDER BY qq.position ASC
  `
    )
    .all(quiz_id);
}

function quizHasQuestion(quiz_id, question_id) {
  const row = db.prepare('SELECT 1 as ok FROM quiz_questions WHERE quiz_id=? AND question_id=?').get(quiz_id, question_id);
  return !!row;
}

module.exports = {
  createQuiz,
  listQuizzes,
  getById,
  updateQuiz,
  deleteQuiz,
  addQuizQuestion,
  removeQuizQuestion,
  getQuizQuestions,
  quizHasQuestion
};