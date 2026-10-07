const quizRepo = require('../repos/quizRepo');
const quizAttemptRepo = require('../repos/quizAttemptRepo');
const questionRepo = require('../repos/questionRepo');

function deny(req, res, status, apiMsg) {
  if (req.path.startsWith('/api')) return res.status(status).json({ error: apiMsg });
  if (status === 404) return res.status(404).render('404');
  return res.status(status).render('403');
}

function withinQuizWindow(quiz) {
  if (!quiz) return false;
  const now = Date.now();
  const openAt = quiz.open_at ? Date.parse(quiz.open_at) : null;
  const dueAt = quiz.due_at ? Date.parse(quiz.due_at) : null;
  if (openAt && Number.isFinite(openAt) && now < openAt) return false;
  if (dueAt && Number.isFinite(dueAt) && now > dueAt) return false;
  return true;
}

function startAttempt(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const me = req.session.user;

  const quiz = quizRepo.getById(course_id, quiz_id);
  if (!quiz) return deny(req, res, 404, 'quiz not found');
  if (!withinQuizWindow(quiz)) return deny(req, res, 403, 'quiz is not open');

  const attempt = quizAttemptRepo.startAttempt({ quiz_id, course_id, student_id: me.user_id });
  if (req.path.startsWith('/api')) return res.status(201).json({ attempt });
  res.redirect(`/courses/${course_id}/quizzes/${quiz_id}/attempts/${attempt.attempt_id}`);
}

function attemptPage(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const attempt_id = Number(req.params.attempt_id);
  const me = req.session.user;

  const quiz = quizRepo.getById(course_id, quiz_id);
  if (!quiz) return deny(req, res, 404, 'quiz not found');

  const attempt = quizAttemptRepo.getAttemptForStudent(attempt_id, course_id, quiz_id, me.user_id);
  if (!attempt) return deny(req, res, 404, 'attempt not found');

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

  const me = req.session.user;
  const quiz = quizRepo.getById(course_id, quiz_id);
  if (!quiz) return deny(req, res, 404, 'quiz not found');
  if (!withinQuizWindow(quiz)) return deny(req, res, 403, 'quiz is not open');

  const qid = Number(question_id);
  if (!qid || !quizRepo.quizHasQuestion(quiz_id, qid)) {
    return deny(req, res, 404, 'question not found');
  }

  const attempt = quizAttemptRepo.getAttemptForStudent(attempt_id, course_id, quiz_id, me.user_id);
  if (!attempt) return deny(req, res, 404, 'attempt not found');
  if (attempt.submitted_at) return deny(req, res, 400, 'attempt already submitted');

  const ans = quizAttemptRepo.upsertAnswerForStudent({
    attempt_id,
    course_id,
    quiz_id,
    student_id: me.user_id,
    question_id: qid,
    answer_json: answer_json || ''
  });

  if (!ans) return deny(req, res, 400, 'cannot record answer');

  if (req.path.startsWith('/api')) return res.status(201).json({ answer: ans });
  res.redirect(`/courses/${course_id}/quizzes/${quiz_id}/attempts/${attempt_id}`);
}

function submitAttempt(req, res) {
  const course_id = Number(req.params.course_id);
  const quiz_id = Number(req.params.quiz_id);
  const attempt_id = Number(req.params.attempt_id);
  const me = req.session.user;

  const quiz = quizRepo.getById(course_id, quiz_id);
  if (!quiz) return deny(req, res, 404, 'quiz not found');
  if (!withinQuizWindow(quiz)) return deny(req, res, 403, 'quiz is not open');

  const attempt = quizAttemptRepo.submitAttemptForStudent(attempt_id, course_id, quiz_id, me.user_id);
  if (!attempt) return deny(req, res, 404, 'attempt not found');

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