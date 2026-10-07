const courseRepository = require('../repositories/courseRepository');
const quizRepository = require('../repositories/quizRepository');
const questionRepository = require('../repositories/questionRepository');
const membershipRepository = require('../repositories/membershipRepository');

function showNewQuiz(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('quiz_new', { course, error: null });
}

function createQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  const { title, description, open_at, due_at } = req.body;
  if (!title) return res.status(400).render('quiz_new', { course, error: 'title is required.' });
  const quiz = quizRepository.createQuiz({ course_id: courseId, created_by: req.currentUser.user_id, title, description, open_at, due_at });
  return res.redirect(`/courses/${courseId}/quizzes/${quiz.quiz_id}`);
}

function apiCreateQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const { title, description, open_at, due_at } = req.body;
  if (!title) return res.status(400).json({ success: false, error: 'title is required' });
  const quiz = quizRepository.createQuiz({ course_id: courseId, created_by: req.currentUser.user_id, title, description, open_at, due_at });
  return res.status(201).json({ success: true, quiz });
}

function listQuizzes(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  res.render('quizzes_list', { course, quizzes: quizRepository.listByCourse(courseId) });
}

function apiListQuizzes(req, res) {
  res.json({ success: true, quizzes: quizRepository.listByCourse(Number(req.params.course_id)) });
}

function getQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const course = courseRepository.findById(courseId);
  const quiz = quizRepository.findById(courseId, quizId);
  if (!course || !quiz) return res.status(404).send('Quiz not found');
  const quizQuestions = quizRepository.listQuizQuestions(quizId);
  res.render('quiz_show', { course, quiz, quizQuestions });
}

function apiGetQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const quiz = quizRepository.findById(courseId, quizId);
  if (!quiz) return res.status(404).json({ success: false, error: 'Quiz not found' });
  res.json({ success: true, quiz, quiz_questions: quizRepository.listQuizQuestions(quizId) });
}

function showEditQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const course = courseRepository.findById(courseId);
  const quiz = quizRepository.findById(courseId, quizId);
  if (!course || !quiz) return res.status(404).send('Quiz not found');
  res.render('quiz_edit', { course, quiz, error: null });
}

function updateQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const course = courseRepository.findById(courseId);
  const existing = quizRepository.findById(courseId, quizId);
  if (!course || !existing) return res.status(404).send('Quiz not found');
  const { title, description, open_at, due_at } = req.body;
  if (!title) return res.status(400).render('quiz_edit', { course, quiz: existing, error: 'title is required.' });
  quizRepository.updateQuiz(courseId, quizId, { title, description, open_at, due_at });
  return res.redirect(`/courses/${courseId}/quizzes/${quizId}`);
}

function apiUpdateQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const existing = quizRepository.findById(courseId, quizId);
  if (!existing) return res.status(404).json({ success: false, error: 'Quiz not found' });
  const { title, description, open_at, due_at } = req.body;
  if (!title) return res.status(400).json({ success: false, error: 'title is required' });
  const quiz = quizRepository.updateQuiz(courseId, quizId, { title, description, open_at, due_at });
  return res.json({ success: true, quiz });
}

function deleteQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  if (!quizRepository.findById(courseId, quizId)) return res.status(404).send('Quiz not found');
  quizRepository.deleteQuiz(courseId, quizId);
  return res.redirect(`/courses/${courseId}/quizzes`);
}

function apiDeleteQuiz(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  if (!quizRepository.findById(courseId, quizId)) return res.status(404).json({ success: false, error: 'Quiz not found' });
  quizRepository.deleteQuiz(courseId, quizId);
  return res.json({ success: true });
}

function showConfigureQuizQuestions(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const course = courseRepository.findById(courseId);
  const quiz = quizRepository.findById(courseId, quizId);
  if (!course || !quiz) return res.status(404).send('Quiz not found');
  res.render('quiz_questions_config', {
    course,
    quiz,
    availableQuestions: questionRepository.listByCourse(courseId),
    quizQuestions: quizRepository.listQuizQuestions(quizId)
  });
}

function configureQuizQuestions(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const { question_id, points, position } = req.body;
  if (!quizRepository.findById(courseId, quizId)) return res.status(404).send('Quiz not found');
  if (!questionRepository.findById(courseId, Number(question_id))) return res.status(404).send('Question not found');
  quizRepository.addQuizQuestion(quizId, Number(question_id), Number(points || 1), Number(position || 1));
  return res.redirect(`/courses/${courseId}/quizzes/${quizId}/questions`);
}

function apiConfigureQuizQuestions(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const { question_id, points, position } = req.body;
  if (!quizRepository.findById(courseId, quizId)) return res.status(404).json({ success: false, error: 'Quiz not found' });
  if (!questionRepository.findById(courseId, Number(question_id))) return res.status(404).json({ success: false, error: 'Question not found' });
  const quizQuestions = quizRepository.addQuizQuestion(quizId, Number(question_id), Number(points || 1), Number(position || 1));
  return res.status(201).json({ success: true, quiz_questions: quizQuestions });
}

function apiRemoveQuizQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const questionId = Number(req.params.question_id);
  if (!quizRepository.findById(courseId, quizId)) return res.status(404).json({ success: false, error: 'Quiz not found' });
  quizRepository.removeQuizQuestion(quizId, questionId);
  return res.json({ success: true });
}

function startAttempt(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const quiz = quizRepository.findById(courseId, quizId);
  if (!quiz) return res.status(404).send('Quiz not found');
  const membership = membershipRepository.findByCourseAndUser(courseId, req.currentUser.user_id);
  const role = membership && membership.role_in_course;
  if (role !== 'student') return res.status(403).send('Student role required');
  const attempt = quizRepository.createAttempt({ quiz_id: quizId, course_id: courseId, student_id: req.currentUser.user_id });
  return res.redirect(`/my/quizzes`);
}

function apiStartAttempt(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const quiz = quizRepository.findById(courseId, quizId);
  if (!quiz) return res.status(404).json({ success: false, error: 'Quiz not found' });
  const membership = membershipRepository.findByCourseAndUser(courseId, req.currentUser.user_id);
  const role = membership && membership.role_in_course;
  if (role !== 'student') return res.status(403).json({ success: false, error: 'Student role required' });
  const attempt = quizRepository.createAttempt({ quiz_id: quizId, course_id: courseId, student_id: req.currentUser.user_id });
  return res.status(201).json({ success: true, attempt });
}

function answerQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const attemptId = Number(req.params.attempt_id);
  const { question_id, answer_json } = req.body;
  const attempt = quizRepository.findAttemptById(courseId, quizId, attemptId);
  if (!attempt) return res.status(404).send('Attempt not found');
  if (attempt.student_id !== req.currentUser.user_id) return res.status(403).send('Own attempt required');
  if (attempt.submitted_at) return res.status(400).send('Attempt already submitted');
  quizRepository.saveAnswer({ attempt_id: attemptId, question_id: Number(question_id), answer_json: typeof answer_json === 'string' ? answer_json : JSON.stringify(answer_json) });
  return res.redirect('/my/quizzes');
}

function apiAnswerQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const attemptId = Number(req.params.attempt_id);
  const { question_id, answer_json } = req.body;
  const attempt = quizRepository.findAttemptById(courseId, quizId, attemptId);
  if (!attempt) return res.status(404).json({ success: false, error: 'Attempt not found' });
  if (attempt.student_id !== req.currentUser.user_id) return res.status(403).json({ success: false, error: 'Own attempt required' });
  if (attempt.submitted_at) return res.status(400).json({ success: false, error: 'Attempt already submitted' });
  if (!question_id || answer_json === undefined) return res.status(400).json({ success: false, error: 'question_id and answer_json are required' });
  const answers = quizRepository.saveAnswer({ attempt_id: attemptId, question_id: Number(question_id), answer_json: typeof answer_json === 'string' ? answer_json : JSON.stringify(answer_json) });
  return res.status(201).json({ success: true, answers });
}

function submitAttempt(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const attemptId = Number(req.params.attempt_id);
  const attempt = quizRepository.findAttemptById(courseId, quizId, attemptId);
  if (!attempt) return res.status(404).send('Attempt not found');
  if (attempt.student_id !== req.currentUser.user_id) return res.status(403).send('Own attempt required');
  quizRepository.submitAttempt(courseId, quizId, attemptId);
  return res.redirect('/my/quizzes');
}

function apiSubmitAttempt(req, res) {
  const courseId = Number(req.params.course_id);
  const quizId = Number(req.params.quiz_id);
  const attemptId = Number(req.params.attempt_id);
  const attempt = quizRepository.findAttemptById(courseId, quizId, attemptId);
  if (!attempt) return res.status(404).json({ success: false, error: 'Attempt not found' });
  if (attempt.student_id !== req.currentUser.user_id) return res.status(403).json({ success: false, error: 'Own attempt required' });
  const updated = quizRepository.submitAttempt(courseId, quizId, attemptId);
  return res.json({ success: true, attempt: updated });
}

function viewMyAttempts(req, res) {
  res.render('my_quizzes', { attempts: quizRepository.listAttemptsByStudent(req.currentUser.user_id) });
}

function apiViewMyAttempts(req, res) {
  res.json({ success: true, attempts: quizRepository.listAttemptsByStudent(req.currentUser.user_id) });
}

module.exports = {
  showNewQuiz, createQuiz, apiCreateQuiz,
  listQuizzes, apiListQuizzes,
  getQuiz, apiGetQuiz,
  showEditQuiz, updateQuiz, apiUpdateQuiz,
  deleteQuiz, apiDeleteQuiz,
  showConfigureQuizQuestions, configureQuizQuestions, apiConfigureQuizQuestions, apiRemoveQuizQuestion,
  startAttempt, apiStartAttempt,
  answerQuestion, apiAnswerQuestion,
  submitAttempt, apiSubmitAttempt,
  viewMyAttempts, apiViewMyAttempts
};
