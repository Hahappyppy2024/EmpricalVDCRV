const { startFreshServer, stopServer, openDb, ApiClient } = require('./api_helpers');

jest.setTimeout(30000);

function ok(res) {
  expect([200, 201, 302]).toContain(res.status);
}

function getUserId(db, username) {
  const row = db.prepare('SELECT user_id FROM users WHERE username=?').get(username);
  return row && row.user_id;
}

describe('Requirement 05: Question Bank + Quiz + Student Attempts', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  });

  afterAll(async () => {
    await stopServer(server);
  });

  test('staff can manage questions/quizzes and student can complete the attempt workflow', async () => {
    const admin = new ApiClient(server.baseUrl);
    const teacher = new ApiClient(server.baseUrl);
    const student = new ApiClient(server.baseUrl);
    const db = openDb();

    try {
      ok(await admin.post('/api/auth/login', {
        json: { username: 'admin', password: 'admin123' },
      }));

      ok(await teacher.post('/api/auth/register', {
        json: {
          username: 'req5_teacher',
          password: 'pass123',
          display_name: 'Req5 Teacher',
        },
      }));

      ok(await student.post('/api/auth/register', {
        json: {
          username: 'req5_student',
          password: 'pass123',
          display_name: 'Req5 Student',
        },
      }));

      const teacherId = getUserId(db, 'req5_teacher');
      const studentId = getUserId(db, 'req5_student');
      expect(teacherId).toBeTruthy();
      expect(studentId).toBeTruthy();

      ok(await admin.post('/api/courses', {
        json: {
          title: 'Req5 Course',
          description: 'Question quiz attempt course',
        },
      }));

      const courseRow = db
        .prepare('SELECT course_id FROM courses WHERE title=? ORDER BY course_id DESC')
        .get('Req5 Course');
      expect(courseRow && courseRow.course_id).toBeTruthy();
      const course_id = courseRow.course_id;

      ok(await admin.post(`/api/courses/${course_id}/members`, {
        json: { user_id: teacherId, role_in_course: 'teacher' },
      }));
      ok(await admin.post(`/api/courses/${course_id}/members`, {
        json: { user_id: studentId, role_in_course: 'student' },
      }));

      // FR-QB1 create_question
      const createQuestion = await teacher.post(`/api/courses/${course_id}/questions`, {
        json: {
          qtype: 'multiple-choice',
          prompt: 'What is 2 + 2?',
          options_json: '["3","4","5"]',
          answer_json: '"4"',
        },
      });
      ok(createQuestion);

      const questionRow = db
        .prepare('SELECT question_id, prompt FROM questions WHERE course_id=? ORDER BY question_id DESC')
        .get(course_id);
      expect(questionRow && questionRow.question_id).toBeTruthy();
      const question_id = questionRow.question_id;

      // FR-QB2 list_questions
      ok(await teacher.get(`/api/courses/${course_id}/questions`));

      // FR-QB3 get_question
      ok(await teacher.get(`/api/courses/${course_id}/questions/${question_id}`));

      // FR-QB4 update_question
      const updateQuestion = await teacher.put(
        `/api/courses/${course_id}/questions/${question_id}`,
        {
          json: {
            qtype: 'multiple-choice',
            prompt: 'What is 3 + 3?',
            options_json: '["5","6","7"]',
            answer_json: '"6"',
          },
        }
      );
      ok(updateQuestion);

      const updatedQuestion = db
        .prepare('SELECT prompt, answer_json FROM questions WHERE question_id=?')
        .get(question_id);
      expect(updatedQuestion.prompt).toBe('What is 3 + 3?');
      expect(updatedQuestion.answer_json).toBe('"6"');

      // FR-QZ1 create_quiz
      const createQuiz = await teacher.post(`/api/courses/${course_id}/quizzes`, {
        json: {
          title: 'Req5 Quiz',
          description: 'Quiz for requirement 05',
          open_at: new Date(Date.now() - 1000).toISOString(),
          due_at: new Date(Date.now() + 3600 * 1000).toISOString(),
        },
      });
      ok(createQuiz);

      const quizRow = db
        .prepare('SELECT quiz_id, title FROM quizzes WHERE course_id=? ORDER BY quiz_id DESC')
        .get(course_id);
      expect(quizRow && quizRow.quiz_id).toBeTruthy();
      const quiz_id = quizRow.quiz_id;

      // FR-QZ2 list_quizzes
      ok(await teacher.get(`/api/courses/${course_id}/quizzes`));

      // FR-QZ3 get_quiz
      ok(await teacher.get(`/api/courses/${course_id}/quizzes/${quiz_id}`));

      // FR-QZ4 update_quiz
      const updateQuiz = await teacher.put(`/api/courses/${course_id}/quizzes/${quiz_id}`, {
        json: {
          title: 'Req5 Quiz Updated',
          description: 'Quiz updated',
          open_at: new Date(Date.now() - 2000).toISOString(),
          due_at: new Date(Date.now() + 7200 * 1000).toISOString(),
        },
      });
      ok(updateQuiz);

      const updatedQuiz = db
        .prepare('SELECT title, description FROM quizzes WHERE quiz_id=?')
        .get(quiz_id);
      expect(updatedQuiz.title).toBe('Req5 Quiz Updated');
      expect(updatedQuiz.description).toBe('Quiz updated');

      // FR-QZ6 configure_quiz_questions
      const configure = await teacher.post(`/api/courses/${course_id}/quizzes/${quiz_id}/questions`, {
        json: {
          question_id,
          points: 2,
          position: 1,
        },
      });
      ok(configure);

      const qqRow = db
        .prepare('SELECT quiz_id, question_id, points, position FROM quiz_questions WHERE quiz_id=? AND question_id=?')
        .get(quiz_id, question_id);
      expect(qqRow && qqRow.quiz_id).toBeTruthy();
      expect(qqRow.points).toBe(2);
      expect(qqRow.position).toBe(1);

      // FR-TQ1 start_attempt
      const startAttempt = await student.post(
        `/api/courses/${course_id}/quizzes/${quiz_id}/attempts/start`,
        { json: {} }
      );
      ok(startAttempt);

      const attemptRow = db
        .prepare('SELECT attempt_id, student_id, submitted_at FROM quiz_attempts WHERE quiz_id=? AND student_id=? ORDER BY attempt_id DESC')
        .get(quiz_id, studentId);
      expect(attemptRow && attemptRow.attempt_id).toBeTruthy();
      const attempt_id = attemptRow.attempt_id;
      expect(attemptRow.submitted_at).toBeNull();

      // FR-TQ2 answer_question
      const answerQuestion = await student.post(
        `/api/courses/${course_id}/quizzes/${quiz_id}/attempts/${attempt_id}/answers`,
        {
          json: {
            question_id,
            answer_json: '"6"',
          },
        }
      );
      ok(answerQuestion);

      const answerRow = db
        .prepare('SELECT answer_id, answer_json FROM quiz_answers WHERE attempt_id=? AND question_id=?')
        .get(attempt_id, question_id);
      expect(answerRow && answerRow.answer_id).toBeTruthy();
      expect(answerRow.answer_json).toBe('"6"');

      // FR-TQ3 submit_attempt
      const submitAttempt = await student.post(
        `/api/courses/${course_id}/quizzes/${quiz_id}/attempts/${attempt_id}/submit`,
        { json: {} }
      );
      ok(submitAttempt);

      const submittedAttempt = db
        .prepare('SELECT submitted_at FROM quiz_attempts WHERE attempt_id=?')
        .get(attempt_id);
      expect(submittedAttempt && submittedAttempt.submitted_at).toBeTruthy();

      // FR-TQ4 view_my_attempts
      ok(await student.get('/api/my/quizzes/attempts'));

      // FR-QZ6 remove configured question
      const removeQuestionConfig = await teacher.delete(
        `/api/courses/${course_id}/quizzes/${quiz_id}/questions/${question_id}`
      );
      ok(removeQuestionConfig);

      const removedConfig = db
        .prepare('SELECT quiz_id FROM quiz_questions WHERE quiz_id=? AND question_id=?')
        .get(quiz_id, question_id);
      expect(removedConfig).toBeUndefined();

      // FR-QZ5 delete_quiz
      const deleteQuiz = await teacher.delete(`/api/courses/${course_id}/quizzes/${quiz_id}`);
      ok(deleteQuiz);

      const deletedQuiz = db
        .prepare('SELECT quiz_id FROM quizzes WHERE quiz_id=?')
        .get(quiz_id);
      expect(deletedQuiz).toBeUndefined();

      // FR-QB5 delete_question
      const deleteQuestion = await teacher.delete(
        `/api/courses/${course_id}/questions/${question_id}`
      );
      ok(deleteQuestion);

      const deletedQuestion = db
        .prepare('SELECT question_id FROM questions WHERE question_id=?')
        .get(question_id);
      expect(deletedQuestion).toBeUndefined();
    } finally {
      try { db.close(); } catch (_) {}
    }
  });
});