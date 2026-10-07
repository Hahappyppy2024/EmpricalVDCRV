const quizRepo = require('../repos/quizRepo');
const questionRepo = require('../repos/questionRepo');

function newQuizForm(req, res) {
  res.render('quizzes/new', { course_id: Number(req.params.course_id) });
}

function createQuiz(req, res) {
  const course_id = Number(req.params.course_id);
  const me = req.session.user;
  const { title, description, open_at, due_at } = req.body;
  const quiz = quizRepo.createQuiz({ course_id, created_by: me.user_id, title, description, open_at, due_at });
  if (req.path.startsWith('/api')) return res.status(201).json({ quiz });
  res.redirect(`/courses/${course_id}/quizzes/${quiz.quiz_id}`);
}

function listQuizzes(req, res) {
  const course_id = Number(req.params.course_id);
  const quizzes = quizRepo.listQuizzes(course_id);
  if (req.path.startsWith('/api')) return res.json({ quizzes });
  res.render('quizzes/list', { course_id, quizzes });
}

function getQuiz(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const quiz = quizRepo.getById(course_id, quiz_id);
  if (!quiz) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'quiz not found' });
    return res.status(404).render('404');
  }
  const quizQuestions = quizRepo.getQuizQuestions(quiz_id);
  if (req.path.startsWith('/api')) return res.json({ quiz, quizQuestions });
  res.render('quizzes/detail', { course_id, quiz, quizQuestions });
}

function editQuizForm(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const quiz = quizRepo.getById(course_id, quiz_id);
  if (!quiz) return res.status(404).render('404');
  res.render('quizzes/edit', { course_id, quiz });
}

function updateQuiz(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const { title, description, open_at, due_at } = req.body;
  const quiz = quizRepo.updateQuiz(course_id, quiz_id, { title, description, open_at, due_at });
  if (req.path.startsWith('/api')) return res.json({ quiz });
  res.redirect(`/courses/${course_id}/quizzes/${quiz_id}`);
}

function deleteQuiz(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  quizRepo.deleteQuiz(course_id, quiz_id);
  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/quizzes`);
}

function configureQuizQuestionsForm(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const quiz = quizRepo.getById(course_id, quiz_id);
  if (!quiz) return res.status(404).render('404');
  const questions = questionRepo.listQuestions(course_id);
  const quizQuestions = quizRepo.getQuizQuestions(quiz_id);
  res.render('quizzes/questions', { course_id, quiz_id, quiz, questions, quizQuestions });
}

function configureQuizQuestions(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const { question_id, points, position } = req.body;
  const list = quizRepo.addQuizQuestion({ quiz_id, question_id: Number(question_id), points: Number(points || 1), position: Number(position || 1) });
  if (req.path.startsWith('/api')) return res.json({ quizQuestions: list });
  res.redirect(`/courses/${course_id}/quizzes/${quiz_id}/questions`);
}

function removeQuizQuestion(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const question_id = Number(req.params.question_id);
  quizRepo.removeQuizQuestion(quiz_id, question_id);
  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/quizzes/${quiz_id}/questions`);
}

module.exports = {
  newQuizForm,
  createQuiz,
  listQuizzes,
  getQuiz,
  editQuizForm,
  updateQuiz,
  deleteQuiz,
  configureQuizQuestionsForm,
  configureQuizQuestions,
  removeQuizQuestion
};
