const test = require('node:test');
const {
  assert,
  startFreshServer,
  stopServer,
  ApiClient,
  login,
  register,
  createCourse,
  addMember,
  createQuestion,
  createQuiz,
  addQuizQuestion
} = require('./_helper');

test('R5 API functional: question bank + quiz + student attempts flow', async (t) => {
  const server = await startFreshServer();
  t.after(async () => stopServer(server));

  const admin = new ApiClient(server.baseUrl);
  await login(admin, 'admin', 'admin123');

  // Create course
  const course = await createCourse(admin, 'Quiz Course', 'Q');
  const courseId = course.course_id;

  // Create student + enroll
  const student = new ApiClient(server.baseUrl);
  const studentUser = await register(student, 'student_q1', 'pass123', 'Student Q1');
  await addMember(admin, courseId, studentUser.user_id, 'student');

  // Question bank CRUD (staff create, member list/get, staff update/delete)
  const q1 = await createQuestion(admin, courseId, {
    qtype: 'single_choice',
    prompt: '2+2=?',
    options: ['3', '4', '5'],
    answer: { correct: '4' }
  });

  const listQ = await admin.get(`/api/courses/${courseId}/questions`);
  assert.equal(listQ.status, 200);
  assert.equal(listQ.data.success, true);
  assert.ok(Array.isArray(listQ.data.questions));
  assert.ok(listQ.data.questions.some(q => q.question_id === q1.question_id));

  const getQ = await student.get(`/api/courses/${courseId}/questions/${q1.question_id}`);
  assert.equal(getQ.status, 200);
  assert.equal(getQ.data.success, true);
  assert.equal(getQ.data.question.question_id, q1.question_id);

  // Student cannot create question
  const studentCreateQ = await student.postJson(`/api/courses/${courseId}/questions`, {
    qtype: 'single_choice',
    prompt: 'should fail',
    options_json: JSON.stringify(['a', 'b']),
    answer_json: JSON.stringify({ correct: 'a' })
  });
  assert.equal(studentCreateQ.status, 403);

  // Staff update question
  const updQ = await admin.putJson(`/api/courses/${courseId}/questions/${q1.question_id}`, {
    qtype: 'single_choice',
    prompt: '2+3=?',
    options_json: JSON.stringify(['4', '5', '6']),
    answer_json: JSON.stringify({ correct: '5' })
  });
  assert.equal(updQ.status, 200);
  assert.equal(updQ.data.success, true);
  assert.equal(updQ.data.question.prompt, '2+3=?');

  // Create quiz and configure questions
  const quiz = await createQuiz(admin, courseId, { title: 'Quiz A', description: 'Quiz A desc' });
  const quizId = quiz.quiz_id;

  const qq = await addQuizQuestion(admin, courseId, quizId, q1.question_id, 2, 1);
  assert.ok(Array.isArray(qq));
  assert.ok(qq.some(row => row.question_id === q1.question_id));

  const getQuiz = await student.get(`/api/courses/${courseId}/quizzes/${quizId}`);
  assert.equal(getQuiz.status, 200);
  assert.equal(getQuiz.data.success, true);
  assert.equal(getQuiz.data.quiz.quiz_id, quizId);
  assert.ok(Array.isArray(getQuiz.data.quiz_questions));

  // Student attempt: start -> answer -> submit -> view my attempts
  const start = await student.postJson(`/api/courses/${courseId}/quizzes/${quizId}/attempts/start`, {});
  assert.equal(start.status, 201);
  assert.equal(start.data.success, true);
  const attemptId = start.data.attempt.attempt_id;
  assert.ok(attemptId);

  const answer = await student.postJson(`/api/courses/${courseId}/quizzes/${quizId}/attempts/${attemptId}/answers`, {
    question_id: q1.question_id,
    answer_json: { correct: '5' }
  });
  assert.equal(answer.status, 201);
  assert.equal(answer.data.success, true);
  assert.ok(Array.isArray(answer.data.answers));
  assert.ok(answer.data.answers.some(a => a.question_id === q1.question_id));

  const submit = await student.postJson(`/api/courses/${courseId}/quizzes/${quizId}/attempts/${attemptId}/submit`, {});
  assert.equal(submit.status, 200);
  assert.equal(submit.data.success, true);
  assert.ok(submit.data.attempt.submitted_at);

  // After submission, answering again should fail
  const answerAfterSubmit = await student.postJson(`/api/courses/${courseId}/quizzes/${quizId}/attempts/${attemptId}/answers`, {
    question_id: q1.question_id,
    answer_json: { correct: '5' }
  });
  assert.equal(answerAfterSubmit.status, 400);

  const myAttempts = await student.get('/api/my/quizzes/attempts');
  assert.equal(myAttempts.status, 200);
  assert.equal(myAttempts.data.success, true);
  assert.ok(Array.isArray(myAttempts.data.attempts));
  assert.ok(myAttempts.data.attempts.some(a => a.attempt_id === attemptId));

  // Cleanup: staff can delete quiz and question
  const delQuiz = await admin.delete(`/api/courses/${courseId}/quizzes/${quizId}`);
  assert.equal(delQuiz.status, 200);
  assert.equal(delQuiz.data.success, true);

  const delQ = await admin.delete(`/api/courses/${courseId}/questions/${q1.question_id}`);
  assert.equal(delQ.status, 200);
  assert.equal(delQ.data.success, true);
});
