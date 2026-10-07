const quizRepo = require('../repos/quizRepo');
const quizAttemptRepo = require('../repos/quizAttemptRepo');
const questionRepo = require('../repos/questionRepo');

function startAttempt(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const me = req.session.user;
  const attempt = quizAttemptRepo.startAttempt({ quiz_id, course_id, student_id: me.user_id });
  if (req.path.startsWith('/api')) return res.status(201).json({ attempt });
  res.redirect(`/courses/${course_id}/quizzes/${quiz_id}/attempts/${attempt.attempt_id}`);
}

function attemptPage(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const attempt_id = Number(req.params.attempt_id);

  const quiz = quizRepo.getById(course_id, quiz_id);
  const attempt = quizAttemptRepo.getAttempt(attempt_id);
  const quizQuestions = quizRepo.getQuizQuestions(quiz_id);
  const answers = quizAttemptRepo.listAnswers(attempt_id);
  const answerMap = new Map(answers.map(a => [a.question_id, a.answer_json]));

  // load question detail for options display (simple)
  const questions = quizQuestions.map(qq => {
    const q = questionRepo.getById(course_id, qq.question_id);
    return { ...qq, ...q, existing_answer: answerMap.get(qq.question_id) || '' };
  });

  res.render('quizzes/attempt', { course_id, quiz_id, quiz, attempt, questions });
}

function answerQuestion(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const attempt_id = Number(req.params.attempt_id);
  const { question_id, answer_json } = req.body;

  const ans = quizAttemptRepo.upsertAnswer({ attempt_id, question_id: Number(question_id), answer_json: answer_json || '' });
  if (req.path.startsWith('/api')) return res.status(201).json({ answer: ans });
  res.redirect(`/courses/${course_id}/quizzes/${quiz_id}/attempts/${attempt_id}`);
}

function submitAttempt(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const attempt_id = Number(req.params.attempt_id);
  const attempt = quizAttemptRepo.submitAttempt(attempt_id);
  if (req.path.startsWith('/api')) return res.json({ attempt });
  res.redirect(`/courses/${course_id}/quizzes/${quiz_id}`);
}

function viewMyAttempts(req, res) {
  const me = req.session.user;
  const attempts = quizAttemptRepo.listMyAttempts(me.user_id);
  if (req.path.startsWith('/api')) return res.json({ attempts });
  res.render('quizzes/my_attempts', { attempts });
}

module.exports = { startAttempt, attemptPage, answerQuestion, submitAttempt, viewMyAttempts };
