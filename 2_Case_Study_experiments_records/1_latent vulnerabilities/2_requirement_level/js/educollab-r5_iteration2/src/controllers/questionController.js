const courseRepository = require('../repositories/courseRepository');
const questionRepository = require('../repositories/questionRepository');

function canViewQuestionAnswers(req) {
  const role = req.courseMembership && req.courseMembership.role_in_course;
  return ['teacher', 'admin', 'assistant', 'senior-assistant'].includes(role);
}

function showNewQuestion(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('question_new', { course, error: null });
}

function createQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  const { qtype, prompt, options_json, answer_json } = req.body;
  if (!qtype || !prompt) return res.status(400).render('question_new', { course, error: 'qtype and prompt are required.' });
  questionRepository.createQuestion({ course_id: courseId, created_by: req.currentUser.user_id, qtype, prompt, options_json, answer_json });
  return res.redirect(`/courses/${courseId}/questions`);
}

function apiCreateQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const { qtype, prompt, options_json, answer_json } = req.body;
  if (!qtype || !prompt) return res.status(400).json({ success: false, error: 'qtype and prompt are required' });
  const question = questionRepository.createQuestion({ course_id: courseId, created_by: req.currentUser.user_id, qtype, prompt, options_json, answer_json });
  return res.status(201).json({ success: true, question });
}

function listQuestions(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  const questions = canViewQuestionAnswers(req)
    ? questionRepository.listByCourse(courseId)
    : questionRepository.listByCourseSafe(courseId);
  res.render('questions_list', { course, questions });
}

function apiListQuestions(req, res) {
  const courseId = Number(req.params.course_id);
  const questions = canViewQuestionAnswers(req)
    ? questionRepository.listByCourse(courseId)
    : questionRepository.listByCourseSafe(courseId);
  res.json({ success: true, questions });
}

function getQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const questionId = Number(req.params.question_id);
  const course = courseRepository.findById(courseId);
  const question = canViewQuestionAnswers(req)
    ? questionRepository.findById(courseId, questionId)
    : questionRepository.findByIdSafe(courseId, questionId);
  if (!course || !question) return res.status(404).send('Question not found');
  res.render('question_show', { course, question });
}

function apiGetQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const questionId = Number(req.params.question_id);
  const question = canViewQuestionAnswers(req)
    ? questionRepository.findById(courseId, questionId)
    : questionRepository.findByIdSafe(courseId, questionId);
  if (!question) return res.status(404).json({ success: false, error: 'Question not found' });
  res.json({ success: true, question });
}

function showEditQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const questionId = Number(req.params.question_id);
  const course = courseRepository.findById(courseId);
  const question = questionRepository.findById(courseId, questionId);
  if (!course || !question) return res.status(404).send('Question not found');
  res.render('question_edit', { course, question, error: null });
}

function updateQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const questionId = Number(req.params.question_id);
  const course = courseRepository.findById(courseId);
  const existing = questionRepository.findById(courseId, questionId);
  if (!course || !existing) return res.status(404).send('Question not found');
  const { qtype, prompt, options_json, answer_json } = req.body;
  if (!qtype || !prompt) return res.status(400).render('question_edit', { course, question: existing, error: 'qtype and prompt are required.' });
  questionRepository.updateQuestion(courseId, questionId, { qtype, prompt, options_json, answer_json });
  return res.redirect(`/courses/${courseId}/questions/${questionId}`);
}

function apiUpdateQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const questionId = Number(req.params.question_id);
  const existing = questionRepository.findById(courseId, questionId);
  if (!existing) return res.status(404).json({ success: false, error: 'Question not found' });
  const { qtype, prompt, options_json, answer_json } = req.body;
  if (!qtype || !prompt) return res.status(400).json({ success: false, error: 'qtype and prompt are required' });
  const question = questionRepository.updateQuestion(courseId, questionId, { qtype, prompt, options_json, answer_json });
  return res.json({ success: true, question });
}

function deleteQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const questionId = Number(req.params.question_id);
  if (!questionRepository.findById(courseId, questionId)) return res.status(404).send('Question not found');
  questionRepository.deleteQuestion(courseId, questionId);
  return res.redirect(`/courses/${courseId}/questions`);
}

function apiDeleteQuestion(req, res) {
  const courseId = Number(req.params.course_id);
  const questionId = Number(req.params.question_id);
  if (!questionRepository.findById(courseId, questionId)) return res.status(404).json({ success: false, error: 'Question not found' });
  questionRepository.deleteQuestion(courseId, questionId);
  return res.json({ success: true });
}

module.exports = {
  showNewQuestion, createQuestion, apiCreateQuestion,
  listQuestions, apiListQuestions,
  getQuestion, apiGetQuestion,
  showEditQuestion, updateQuestion, apiUpdateQuestion,
  deleteQuestion, apiDeleteQuestion
};