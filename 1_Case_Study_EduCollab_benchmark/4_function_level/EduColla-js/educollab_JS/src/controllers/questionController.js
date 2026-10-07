const questionRepo = require('../repos/questionRepo');

function newQuestionForm(req, res) {
  res.render('questions/new', { course_id: Number(req.params.course_id) });
}

function createQuestion(req, res) {
  const course_id = Number(req.params.course_id);
  const me = req.session.user;
  const { qtype, prompt, options_json, answer_json } = req.body;
  const q = questionRepo.createQuestion({
    course_id,
    created_by: me.user_id,
    qtype,
    prompt,
    options_json: options_json || null,
    answer_json: answer_json || null
  });
  if (req.originalUrl.startsWith('/api')) return res.status(201).json({ question: q });
  res.redirect(`/courses/${course_id}/questions`);
}

function listQuestions(req, res) {
  const course_id = Number(req.params.course_id);
  const questions = questionRepo.listQuestions(course_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ questions });
  res.render('questions/list', { course_id, questions });
}

function getQuestion(req, res) {
  const course_id = Number(req.params.course_id);
  const question_id = Number(req.params.question_id);
  const question = questionRepo.getById(course_id, question_id);
  if (!question) {
    if (req.originalUrl.startsWith('/api')) return res.status(404).json({ error: 'question not found' });
    return res.status(404).render('404');
  }
  if (req.originalUrl.startsWith('/api')) return res.json({ question });
  res.render('questions/detail', { course_id, question });
}

function editQuestionForm(req, res) {
  const course_id = Number(req.params.course_id);
  const question_id = Number(req.params.question_id);
  const question = questionRepo.getById(course_id, question_id);
  if (!question) return res.status(404).render('404');
  res.render('questions/edit', { course_id, question });
}

function updateQuestion(req, res) {
  const course_id = Number(req.params.course_id);
  const question_id = Number(req.params.question_id);
  const { qtype, prompt, options_json, answer_json } = req.body;
  const question = questionRepo.updateQuestion(course_id, question_id, { qtype, prompt, options_json, answer_json });
  if (req.originalUrl.startsWith('/api')) return res.json({ question });
  res.redirect(`/courses/${course_id}/questions/${question_id}`);
}

function deleteQuestion(req, res) {
  const course_id = Number(req.params.course_id);
  const question_id = Number(req.params.question_id);
  questionRepo.deleteQuestion(course_id, question_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/questions`);
}

module.exports = { newQuestionForm, createQuestion, listQuestions, getQuestion, editQuestionForm, updateQuestion, deleteQuestion };
