const { startFreshServer, stopServer, openDb, ApiClient } = require('./api_helpers');

jest.setTimeout(30000);

function ok(res) {
  expect([200, 201, 302]).toContain(res.status);
}

function getUserId(db, username) {
  const row = db.prepare('SELECT user_id FROM users WHERE username=?').get(username);
  return row && row.user_id;
}

function getCourseIdByTitle(db, title) {
  const row = db.prepare('SELECT course_id FROM courses WHERE title=? ORDER BY course_id DESC').get(title);
  return row && row.course_id;
}

describe('Project-level API smoke (/api) - tolerant to redirect/HTML', () => {
  let server;

  beforeAll(async () => {
    server = await startFreshServer();
  });

  afterAll(async () => {
    await stopServer(server);
  });

  test('Auth + course/membership/posts/assignments/uploads/quiz flow', async () => {
    const admin = new ApiClient(server.baseUrl);
    const bob = new ApiClient(server.baseUrl);

    const db = openDb();
    try {
      // --------------------
      // Auth: admin login
      // --------------------
      const loginAdmin = await admin.post('/api/auth/login', {
        json: { username: 'admin', password: 'admin123' },
      });
      ok(loginAdmin);

      const adminId = getUserId(db, 'admin');
      expect(adminId).toBeTruthy();

      // --------------------
      // Create course (via /api, but may redirect)
      // --------------------
      const createCourse = await admin.post('/api/courses', {
        json: { title: 'C1', description: 'D1' },
      });
      ok(createCourse);

      const course_id = getCourseIdByTitle(db, 'C1');
      expect(course_id).toBeTruthy();

      // Ensure admin is member (createCourse auto-add)
      const adminMem = db
        .prepare('SELECT membership_id, role_in_course FROM memberships WHERE course_id=? AND user_id=?')
        .get(course_id, adminId);
      expect(adminMem && adminMem.membership_id).toBeTruthy();

      // --------------------
      // Register bob (auto login; may redirect)
      // --------------------
      const regBob = await bob.post('/api/auth/register', {
        json: { username: 'bob', password: 'password', display_name: 'Bob' },
      });
      ok(regBob);

      const bob_id = getUserId(db, 'bob');
      expect(bob_id).toBeTruthy();

      // --------------------
      // Admin adds bob as student
      // --------------------
      const addMember = await admin.post(`/api/courses/${course_id}/members`, {
        json: { user_id: bob_id, role_in_course: 'student' },
      });
      ok(addMember);

      const bobMem = db
        .prepare('SELECT membership_id, role_in_course FROM memberships WHERE course_id=? AND user_id=?')
        .get(course_id, bob_id);
      expect(bobMem && bobMem.membership_id).toBeTruthy();
      expect(bobMem.role_in_course).toBe('student');

      // --------------------
      // Bob creates post
      // --------------------
      const createPost = await bob.post(`/api/courses/${course_id}/posts`, {
        json: { title: 'Hello', body: 'World' },
      });
      ok(createPost);

      const postRow = db
        .prepare('SELECT post_id FROM posts WHERE course_id=? AND author_id=? ORDER BY post_id DESC')
        .get(course_id, bob_id);
      expect(postRow && postRow.post_id).toBeTruthy();
      const post_id = postRow.post_id;

      // --------------------
      // Bob comments
      // --------------------
      const createComment = await bob.post(`/api/courses/${course_id}/posts/${post_id}/comments`, {
        json: { body: 'Nice' },
      });
      ok(createComment);

      const commentRow = db
        .prepare('SELECT comment_id FROM comments WHERE course_id=? AND post_id=? AND author_id=? ORDER BY comment_id DESC')
        .get(course_id, post_id, bob_id);
      expect(commentRow && commentRow.comment_id).toBeTruthy();
      const comment_id = commentRow.comment_id;

      // --------------------
      // Search endpoints (may be JSON OR HTML)
      // Just assert reachable
      // --------------------
      const searchPosts = await bob.get(`/api/courses/${course_id}/search/posts?keyword=Hell`);
      ok(searchPosts);

      const searchComments = await bob.get(`/api/courses/${course_id}/search/comments?keyword=Nic`);
      ok(searchComments);

      // --------------------
      // Admin creates assignment
      // --------------------
      const createAssignment = await admin.post(`/api/courses/${course_id}/assignments`, {
        json: {
          title: 'A1',
          description: 'Asg',
          due_at: new Date(Date.now() + 3600 * 1000).toISOString(),
        },
      });
      ok(createAssignment);

      const asgRow = db
        .prepare('SELECT assignment_id FROM assignments WHERE course_id=? ORDER BY assignment_id DESC')
        .get(course_id);
      expect(asgRow && asgRow.assignment_id).toBeTruthy();
      const assignment_id = asgRow.assignment_id;

      // --------------------
      // Bob submits assignment
      // --------------------
      const createSubmission = await bob.post(
        `/api/courses/${course_id}/assignments/${assignment_id}/submissions`,
        { json: { content_text: 'my work', attachment_upload_id: null } }
      );
      ok(createSubmission);

      const subRow = db
        .prepare(
          'SELECT submission_id, content_text FROM submissions WHERE assignment_id=? AND student_id=?'
        )
        .get(assignment_id, bob_id);
      expect(subRow && subRow.submission_id).toBeTruthy();
      expect(subRow.content_text).toBe('my work');
      const submission_id = subRow.submission_id;

      // /api/my/submissions (may be JSON/HTML) - just assert reachable
      const mySubs = await bob.get('/api/my/submissions');
      ok(mySubs);

      // --------------------
      // Admin uploads a file (multipart)
      // --------------------
      const form = new FormData();
      form.append('file', new Blob([Buffer.from('hello')], { type: 'text/plain' }), 'note.txt');

      const uploadRes = await admin.post(`/api/courses/${course_id}/uploads`, { body: form });
      ok(uploadRes);

      const uploadRow = db
        .prepare('SELECT upload_id, storage_path FROM uploads WHERE course_id=? ORDER BY upload_id DESC')
        .get(course_id);
      expect(uploadRow && uploadRow.upload_id).toBeTruthy();
      expect(uploadRow.storage_path).toBeTruthy();
      const upload_id = uploadRow.upload_id;

      // Bob downloads upload
      const dlRes = await fetch(
        `${server.baseUrl}/api/courses/${course_id}/uploads/${upload_id}/download`,
        { headers: { cookie: bob.cookie }, redirect: 'manual' }
      );
      expect([200, 302]).toContain(dlRes.status);

      // 如果下载点也重定向（不太常见），可以先不强行断言内容
      if (dlRes.status === 200) {
        const dlText = await dlRes.text();
        expect(dlText).toBe('hello');
      }

      // --------------------
      // Admin creates question
      // --------------------
      const createQuestion = await admin.post(`/api/courses/${course_id}/questions`, {
        json: {
          qtype: 'multiple-choice',
          prompt: '2+2?',
          options_json: '["3","4","5"]',
          answer_json: '"4"',
        },
      });
      ok(createQuestion);

      const qRow = db
        .prepare('SELECT question_id FROM questions WHERE course_id=? ORDER BY question_id DESC')
        .get(course_id);
      expect(qRow && qRow.question_id).toBeTruthy();
      const question_id = qRow.question_id;

      // --------------------
      // Admin creates quiz
      // --------------------
      const createQuiz = await admin.post(`/api/courses/${course_id}/quizzes`, {
        json: {
          title: 'Quiz1',
          description: 'Desc',
          open_at: new Date(Date.now() - 1000).toISOString(),
          due_at: new Date(Date.now() + 3600 * 1000).toISOString(),
        },
      });
      ok(createQuiz);

      const quizRow = db
        .prepare('SELECT quiz_id FROM quizzes WHERE course_id=? ORDER BY quiz_id DESC')
        .get(course_id);
      expect(quizRow && quizRow.quiz_id).toBeTruthy();
      const quiz_id = quizRow.quiz_id;

      // Configure quiz question
      const cfg = await admin.post(`/api/courses/${course_id}/quizzes/${quiz_id}/questions`, {
        json: { question_id, points: 1, position: 1 },
      });
      ok(cfg);

      const qq = db
        .prepare('SELECT quiz_id, question_id, points, position FROM quiz_questions WHERE quiz_id=? AND question_id=?')
        .get(quiz_id, question_id);
      expect(qq && qq.quiz_id).toBeTruthy();
      expect(qq.points).toBe(1);
      expect(qq.position).toBe(1);

      // --------------------
      // Bob starts attempt
      // --------------------
      const startAttempt = await bob.post(`/api/courses/${course_id}/quizzes/${quiz_id}/attempts/start`, {
        json: {},
      });
      ok(startAttempt);

      const attemptRow = db
        .prepare('SELECT attempt_id, started_at, submitted_at FROM quiz_attempts WHERE quiz_id=? AND student_id=? ORDER BY attempt_id DESC')
        .get(quiz_id, bob_id);
      expect(attemptRow && attemptRow.attempt_id).toBeTruthy();
      const attempt_id = attemptRow.attempt_id;

      // Bob answers
      const answerRes = await bob.post(
        `/api/courses/${course_id}/quizzes/${quiz_id}/attempts/${attempt_id}/answers`,
        { json: { question_id, answer_json: '"4"' } }
      );
      ok(answerRes);

      const ansRow = db
        .prepare('SELECT answer_id, answer_json FROM quiz_answers WHERE attempt_id=? AND question_id=?')
        .get(attempt_id, question_id);
      expect(ansRow && ansRow.answer_id).toBeTruthy();
      expect(ansRow.answer_json).toBe('"4"');

      // Bob submits attempt
      const submitAttempt = await bob.post(
        `/api/courses/${course_id}/quizzes/${quiz_id}/attempts/${attempt_id}/submit`,
        { json: {} }
      );
      ok(submitAttempt);

      const att2 = db.prepare('SELECT submitted_at FROM quiz_attempts WHERE attempt_id=?').get(attempt_id);
      expect(att2 && att2.submitted_at).toBeTruthy();

      // /api/my/quizzes/attempts (reachable)
      const myAttempts = await bob.get('/api/my/quizzes/attempts');
      ok(myAttempts);

      // Sanity: created entities exist
      expect(post_id).toBeTruthy();
      expect(comment_id).toBeTruthy();
      expect(submission_id).toBeTruthy();
    } finally {
      try { db.close(); } catch (_) {}
    }
  });
});