const { db } = require('../db');

function createQuiz({ course_id, created_by, title, description, open_at, due_at }) {
  const result = db.prepare(`
    INSERT INTO quizzes (course_id, created_by, title, description, open_at, due_at)
    VALUES (?, ?, ?, ?, ?, ?)
  `).run(course_id, created_by, title, description || '', open_at || null, due_at || null);
  return findById(course_id, result.lastInsertRowid);
}

function listByCourse(courseId) {
  return db.prepare(`
    SELECT q.*, u.username AS creator_username, u.display_name AS creator_display_name
    FROM quizzes q
    JOIN users u ON u.user_id = q.created_by
    WHERE q.course_id = ?
    ORDER BY q.quiz_id DESC
  `).all(courseId);
}

function findById(courseId, quizId) {
  return db.prepare(`
    SELECT q.*, u.username AS creator_username, u.display_name AS creator_display_name
    FROM quizzes q
    JOIN users u ON u.user_id = q.created_by
    WHERE q.course_id = ? AND q.quiz_id = ?
  `).get(courseId, quizId);
}

function updateQuiz(courseId, quizId, { title, description, open_at, due_at }) {
  db.prepare(`
    UPDATE quizzes
    SET title = ?, description = ?, open_at = ?, due_at = ?, updated_at = CURRENT_TIMESTAMP
    WHERE course_id = ? AND quiz_id = ?
  `).run(title, description || '', open_at || null, due_at || null, courseId, quizId);
  return findById(courseId, quizId);
}

function deleteQuiz(courseId, quizId) {
  return db.prepare(`DELETE FROM quizzes WHERE course_id = ? AND quiz_id = ?`).run(courseId, quizId);
}

function addQuizQuestion(quizId, questionId, points, position) {
  db.prepare(`
    INSERT INTO quiz_questions (quiz_id, question_id, points, position)
    VALUES (?, ?, ?, ?)
    ON CONFLICT(quiz_id, question_id) DO UPDATE SET points = excluded.points, position = excluded.position
  `).run(quizId, questionId, points || 1, position || 1);
  return listQuizQuestions(quizId);
}

function removeQuizQuestion(quizId, questionId) {
  return db.prepare(`DELETE FROM quiz_questions WHERE quiz_id = ? AND question_id = ?`).run(quizId, questionId);
}

function listQuizQuestions(quizId) {
  return db.prepare(`
    SELECT qq.quiz_id, qq.question_id, qq.points, qq.position,
           q.course_id, q.qtype, q.prompt, q.options_json, q.answer_json,
           q.created_at, q.updated_at
    FROM quiz_questions qq
    JOIN questions q ON q.question_id = qq.question_id
    WHERE qq.quiz_id = ?
    ORDER BY qq.position ASC, qq.question_id ASC
  `).all(quizId);
}

function listQuizQuestionsSafe(quizId) {
  return db.prepare(`
    SELECT qq.quiz_id, qq.question_id, qq.points, qq.position,
           q.course_id, q.qtype, q.prompt, q.options_json,
           q.created_at, q.updated_at
    FROM quiz_questions qq
    JOIN questions q ON q.question_id = qq.question_id
    WHERE qq.quiz_id = ?
    ORDER BY qq.position ASC, qq.question_id ASC
  `).all(quizId);
}

function createAttempt({ quiz_id, course_id, student_id }) {
  const result = db.prepare(`
    INSERT INTO quiz_attempts (quiz_id, course_id, student_id)
    VALUES (?, ?, ?)
  `).run(quiz_id, course_id, student_id);
  return findAttemptById(course_id, quiz_id, result.lastInsertRowid);
}

function findAttemptById(courseId, quizId, attemptId) {
  return db.prepare(`
    SELECT qa.*, u.username AS student_username, u.display_name AS student_display_name
    FROM quiz_attempts qa
    JOIN users u ON u.user_id = qa.student_id
    WHERE qa.course_id = ? AND qa.quiz_id = ? AND qa.attempt_id = ?
  `).get(courseId, quizId, attemptId);
}

function isQuestionInQuiz(quizId, questionId) {
  const row = db.prepare(`
    SELECT 1
    FROM quiz_questions
    WHERE quiz_id = ? AND question_id = ?
  `).get(quizId, questionId);
  return !!row;
}

function saveAnswer({ attempt_id, question_id, answer_json }) {
  const attempt = db.prepare(`SELECT quiz_id FROM quiz_attempts WHERE attempt_id = ?`).get(attempt_id);
  if (!attempt || !isQuestionInQuiz(attempt.quiz_id, question_id)) {
    return null;
  }
  db.prepare(`
    INSERT INTO quiz_answers (attempt_id, question_id, answer_json)
    VALUES (?, ?, ?)
    ON CONFLICT(attempt_id, question_id) DO UPDATE SET answer_json = excluded.answer_json, created_at = CURRENT_TIMESTAMP
  `).run(attempt_id, question_id, answer_json);
  return listAnswersByAttempt(attempt_id);
}

function listAnswersByAttempt(attemptId) {
  return db.prepare(`
    SELECT * FROM quiz_answers WHERE attempt_id = ? ORDER BY answer_id ASC
  `).all(attemptId);
}

function submitAttempt(courseId, quizId, attemptId) {
  db.prepare(`
    UPDATE quiz_attempts SET submitted_at = CURRENT_TIMESTAMP
    WHERE course_id = ? AND quiz_id = ? AND attempt_id = ?
  `).run(courseId, quizId, attemptId);
  return findAttemptById(courseId, quizId, attemptId);
}

function listAttemptsByStudent(studentId) {
  return db.prepare(`
    SELECT qa.*, q.title AS quiz_title, q.description AS quiz_description, c.title AS course_title
    FROM quiz_attempts qa
    JOIN quizzes q ON q.quiz_id = qa.quiz_id
    JOIN courses c ON c.course_id = qa.course_id
    WHERE qa.student_id = ?
    ORDER BY qa.attempt_id DESC
  `).all(studentId);
}

module.exports = {
  createQuiz, listByCourse, findById, updateQuiz, deleteQuiz,
  addQuizQuestion, removeQuizQuestion, listQuizQuestions, listQuizQuestionsSafe, isQuestionInQuiz,
  createAttempt, findAttemptById, saveAnswer, listAnswersByAttempt,
  submitAttempt, listAttemptsByStudent
};