const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const net = require('net');
const os = require('os');
const Database = require('better-sqlite3');

let CURRENT_APP_ROOT = null;

async function getFreePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      server.close(() => resolve(port));
    });
    server.on('error', reject);
  });
}

async function waitForServer(baseUrl, proc, timeoutMs = 20000) {
  const start = Date.now();
  let lastErr = null;
  let stdoutBuf = '';
  let stderrBuf = '';

  const onStdout = (d) => {
    const s = String(d);
    stdoutBuf += s;
    process.stderr.write(s);
  };
  const onStderr = (d) => {
    const s = String(d);
    stderrBuf += s;
    process.stderr.write(s);
  };

  proc.stdout.on('data', onStdout);
  proc.stderr.on('data', onStderr);

  try {
    while (Date.now() - start < timeoutMs) {
      if (proc.exitCode !== null) {
        throw new Error(
          `server exited early with code=${proc.exitCode}\nSTDOUT:\n${stdoutBuf}\nSTDERR:\n${stderrBuf}`
        );
      }

      try {
        const res = await fetch(baseUrl + '/', {
          method: 'GET',
          redirect: 'manual',
        });
        if (res.status < 500) return;
      } catch (e) {
        lastErr = e;
      }

      await new Promise((r) => setTimeout(r, 300));
    }

    throw new Error(
      `Server did not become ready within ${timeoutMs}ms: ${lastErr?.message || 'fetch failed'}\nSTDOUT:\n${stdoutBuf}\nSTDERR:\n${stderrBuf}`
    );
  } finally {
    proc.stdout.off('data', onStdout);
    proc.stderr.off('data', onStderr);
  }
}

function copyDirSync(src, dest) {
  fs.mkdirSync(dest, { recursive: true });

  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);

    if (entry.isDirectory()) {
      if (entry.name === '.git') continue;
      if (entry.name === 'node_modules') continue;
      copyDirSync(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

function linkNodeModules(sourceRoot, appRoot) {
  const srcNodeModules = path.join(sourceRoot, 'node_modules');
  const dstNodeModules = path.join(appRoot, 'node_modules');

  if (!fs.existsSync(srcNodeModules)) {
    throw new Error(`node_modules not found at ${srcNodeModules}`);
  }

  if (!fs.existsSync(dstNodeModules)) {
    fs.symlinkSync(srcNodeModules, dstNodeModules, 'junction');
  }
}

async function startFreshServer() {
  const sourceRoot = path.resolve(__dirname, '..', '..');
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'educollab-js-test-'));
  const appRoot = path.join(tempRoot, 'app');

  copyDirSync(sourceRoot, appRoot);
  linkNodeModules(sourceRoot, appRoot);

  const dataDir = path.join(appRoot, 'data');
  const dbPath = path.join(dataDir, 'app.db');
  const uploadsDir = path.join(dataDir, 'uploads');

  fs.mkdirSync(dataDir, { recursive: true });

  if (fs.existsSync(dbPath)) {
    fs.rmSync(dbPath, { force: true });
  }

  if (fs.existsSync(uploadsDir)) {
    fs.rmSync(uploadsDir, { recursive: true, force: true });
  }

  const port = await getFreePort();
  const host = '127.0.0.1';
  const baseUrl = `http://${host}:${port}`;

  const proc = spawn(process.execPath, ['app.js'], {
    cwd: appRoot,
    env: {
      ...process.env,
      PORT: String(port),
      HOST: host,
    },
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  await waitForServer(baseUrl, proc);

  CURRENT_APP_ROOT = appRoot;

  return { proc, baseUrl, appRoot, tempRoot };
}

async function stopServer(server) {
  if (!server || !server.proc) return;

  server.proc.kill('SIGTERM');
  await new Promise((r) => setTimeout(r, 500));

  if (server.proc.exitCode === null) {
    server.proc.kill('SIGKILL');
  }

  if (server.tempRoot && fs.existsSync(server.tempRoot)) {
    try {
      fs.rmSync(server.tempRoot, { recursive: true, force: true });
    } catch (_) {}
  }

  if (CURRENT_APP_ROOT === server.appRoot) {
    CURRENT_APP_ROOT = null;
  }
}

class ApiClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this.cookie = '';
    this.currentUser = null;
  }

  async request(method, route, body) {
    const headers = {
      accept: 'application/json',
    };

    if (this.cookie) {
      headers.cookie = this.cookie;
    }

    let payload;
    if (body !== undefined) {
      headers['content-type'] = 'application/json';
      payload = JSON.stringify(body);
    }

    const res = await fetch(this.baseUrl + route, {
      method,
      headers,
      body: payload,
      redirect: 'manual',
    });

    const setCookie = res.headers.get('set-cookie');
    if (setCookie) {
      this.cookie = setCookie.split(';')[0];
    }

    const text = await res.text();

    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch {
      data = null;
    }

    return {
      status: res.status,
      data,
      text,
      headers: res.headers,
    };
  }

  async requestMultipart(method, route, formData) {
    const headers = {};

    if (this.cookie) {
      headers.cookie = this.cookie;
    }

    const res = await fetch(this.baseUrl + route, {
      method,
      headers,
      body: formData,
      redirect: 'manual',
    });

    const setCookie = res.headers.get('set-cookie');
    if (setCookie) {
      this.cookie = setCookie.split(';')[0];
    }

    const text = await res.text();

    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch {
      data = null;
    }

    return {
      status: res.status,
      data,
      text,
      headers: res.headers,
    };
  }

  get(route) {
    return this.request('GET', route);
  }

  post(route, body) {
    return this.request('POST', route, body);
  }

  put(route, body) {
    return this.request('PUT', route, body);
  }

  delete(route, body) {
    return this.request('DELETE', route, body);
  }
}

function error(prefix, res) {
  return `${prefix}: ${JSON.stringify({
    status: res.status,
    data: res.data,
    text: res.text,
    location: res.headers.get('location'),
    hasSetCookie: !!res.headers.get('set-cookie'),
  })}`;
}

function getDb() {
  if (!CURRENT_APP_ROOT) {
    throw new Error('CURRENT_APP_ROOT is not set');
  }

  const dbPath = path.join(CURRENT_APP_ROOT, 'data', 'app.db');
  if (!fs.existsSync(dbPath)) {
    throw new Error(`db not found at ${dbPath}`);
  }

  return new Database(dbPath, { readonly: true });
}

async function getCurrentUser(client, usernameHint = null) {
  if (client && client.currentUser) {
    return client.currentUser;
  }

  const db = getDb();

  try {
    if (usernameHint) {
      const row = db
        .prepare('SELECT user_id, username, display_name FROM users WHERE username = ?')
        .get(usernameHint);

      if (row) return row;
    }

    throw new Error(
      `getCurrentUser failed: user not found${usernameHint ? ` for username=${usernameHint}` : ''}`
    );
  } finally {
    db.close();
  }
}

async function register(client, username, password = 'pass123', display_name = username) {
  const res = await client.post('/api/auth/register', {
    username,
    password,
    display_name,
  });

  const hasCookie = !!res.headers.get('set-cookie');

  if ((res.status === 200 || res.status === 201) && res.data?.user) {
    client.currentUser = res.data.user;
    return res.data.user;
  }

  if ((res.status === 200 || res.status === 201 || res.status === 302 || res.status === 303) && hasCookie) {
    const user = await getCurrentUser(client, username);
    client.currentUser = user;
    return user;
  }

  throw new Error(error('register failed', res));
}

async function login(client, username, password = 'pass123') {
  const res = await client.post('/api/auth/login', {
    username,
    password,
  });

  const hasCookie = !!res.headers.get('set-cookie');

  if (res.status === 200 && res.data?.user) {
    client.currentUser = res.data.user;
    return res.data.user;
  }

  if ((res.status === 200 || res.status === 302 || res.status === 303) && hasCookie) {
    const user = await getCurrentUser(client, username);
    client.currentUser = user;
    return user;
  }

  throw new Error(error('login failed', res));
}

async function createCourse(client, title = 'Course', description = 'Desc') {
  const res = await client.post('/api/courses', { title, description });

  if (res.status === 201 && res.data?.course) {
    return res.data.course;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    const location = res.headers.get('location');
    const m = location.match(/\/courses\/(\d+)/);
    if (m) {
      return {
        course_id: Number(m[1]),
        title,
        description,
      };
    }
  }

  throw new Error(error('createCourse failed', res));
}

async function addMember(client, courseId, userId, role) {
  const res = await client.post(`/api/courses/${courseId}/members`, {
    user_id: userId,
    role_in_course: role,
  });

  if (res.status === 201 && res.data?.membership) {
    return res.data.membership;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    return {
      course_id: Number(courseId),
      user_id: Number(userId),
      role_in_course: role,
    };
  }

  throw new Error(error('addMember failed', res));
}

async function createPost(client, courseId, title = 'Post title', body = 'Post body') {
  const res = await client.post(`/api/courses/${courseId}/posts`, {
    title,
    body,
  });

  if (res.status === 201 && res.data?.post) {
    return res.data.post;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    const location = res.headers.get('location');
    const m = location.match(/\/courses\/(\d+)\/posts\/(\d+)/);
    if (m) {
      return {
        course_id: Number(m[1]),
        post_id: Number(m[2]),
        title,
        body,
      };
    }
  }

  throw new Error(error('createPost failed', res));
}

async function createComment(client, courseId, postId, body = 'Comment body') {
  const me = await getCurrentUser(client);

  const res = await client.post(`/api/courses/${courseId}/posts/${postId}/comments`, {
    body,
  });

  if (res.status === 201 && res.data?.comment) {
    return res.data.comment;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    const created = getLatestCommentByAuthorAndPostFromDb(postId, me.user_id, body);
    if (created) {
      return created;
    }
  }

  throw new Error(error('createComment failed', res));
}

async function createAssignment(
  client,
  courseId,
  title = 'HW1',
  description = 'desc',
  due_at = '2099-01-01T00:00:00.000Z'
) {
  const res = await client.post(`/api/courses/${courseId}/assignments`, {
    title,
    description,
    due_at,
  });

  if (res.status === 201 && res.data?.assignment) {
    return res.data.assignment;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    const location = res.headers.get('location');
    const m = location.match(/\/courses\/(\d+)\/assignments\/(\d+)/);
    if (m) {
      return {
        course_id: Number(m[1]),
        assignment_id: Number(m[2]),
        title,
        description,
        due_at,
      };
    }
  }

  throw new Error(error('createAssignment failed', res));
}

async function uploadFile(client, courseId, filename, content, mimeType = 'text/plain') {
  const me = await getCurrentUser(client);

  const form = new FormData();
  const blob = new Blob([content], { type: mimeType });
  form.append('file', blob, filename);

  const res = await client.requestMultipart(
    'POST',
    `/api/courses/${courseId}/uploads`,
    form
  );

  if ((res.status === 200 || res.status === 201) && res.data?.upload) {
    return res.data.upload;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    const uploaded = getLatestUploadByCourseAndUserFromDb(courseId, me.user_id, filename);
    if (uploaded) {
      return uploaded;
    }
  }

  throw new Error(error('uploadFile failed', res));
}

function getPostByIdFromDb(postId) {
  const db = getDb();
  try {
    return db
      .prepare('SELECT post_id, course_id, author_id, title, body FROM posts WHERE post_id = ?')
      .get(postId);
  } finally {
    db.close();
  }
}

function getCommentByIdFromDb(commentId) {
  const db = getDb();
  try {
    return db
      .prepare('SELECT comment_id, course_id, post_id, author_id, body FROM comments WHERE comment_id = ?')
      .get(commentId);
  } finally {
    db.close();
  }
}

function getLatestCommentByAuthorAndPostFromDb(postId, authorId, body) {
  const db = getDb();
  try {
    return db.prepare(`
      SELECT comment_id, course_id, post_id, author_id, body
      FROM comments
      WHERE post_id = ? AND author_id = ? AND body = ?
      ORDER BY comment_id DESC
      LIMIT 1
    `).get(postId, authorId, body);
  } finally {
    db.close();
  }
}

function getSubmissionByIdFromDb(submissionId) {
  const db = getDb();
  try {
    return db
      .prepare(`
        SELECT submission_id, course_id, assignment_id, student_id, content_text, attachment_upload_id
        FROM submissions
        WHERE submission_id = ?
      `)
      .get(submissionId);
  } finally {
    db.close();
  }
}

function getLatestSubmissionByAssignmentAndStudentFromDb(assignmentId, studentId) {
  const db = getDb();
  try {
    return db
      .prepare(`
        SELECT submission_id, course_id, assignment_id, student_id, content_text, attachment_upload_id
        FROM submissions
        WHERE assignment_id = ? AND student_id = ?
        ORDER BY submission_id DESC
        LIMIT 1
      `)
      .get(assignmentId, studentId);
  } finally {
    db.close();
  }
}

function getLatestUploadByCourseAndUserFromDb(courseId, userId, originalName = null) {
  const db = getDb();
  try {
    if (originalName) {
      return db.prepare(`
        SELECT upload_id, course_id, uploaded_by, original_name, storage_path
        FROM uploads
        WHERE course_id = ? AND uploaded_by = ? AND original_name = ?
        ORDER BY upload_id DESC
        LIMIT 1
      `).get(courseId, userId, originalName);
    }

    return db.prepare(`
      SELECT upload_id, course_id, uploaded_by, original_name, storage_path
      FROM uploads
      WHERE course_id = ? AND uploaded_by = ?
      ORDER BY upload_id DESC
      LIMIT 1
    `).get(courseId, userId);
  } finally {
    db.close();
  }
}
async function createQuestion(
  client,
  courseId,
  {
    qtype = 'single_choice',
    prompt = 'Question?',
    options_json = '["A","B"]',
    answer_json = '{"correct":"A"}',
  } = {}
) {
  const res = await client.post(`/api/courses/${courseId}/questions`, {
    qtype,
    prompt,
    options_json,
    answer_json,
  });

  if (res.status === 201 && res.data?.question) {
    return res.data.question;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    const db = getDb();
    try {
      const row = db.prepare(`
        SELECT question_id, course_id, qtype, prompt
        FROM questions
        WHERE course_id = ? AND prompt = ?
        ORDER BY question_id DESC
        LIMIT 1
      `).get(courseId, prompt);
      if (row) return row;
    } finally {
      db.close();
    }
  }

  throw new Error(error('createQuestion failed', res));
}

async function createQuiz(
  client,
  courseId,
  {
    title = 'Quiz',
    description = 'desc',
    open_at = '2000-01-01T00:00:00.000Z',
    due_at = '2099-01-01T00:00:00.000Z',
  } = {}
) {
  const res = await client.post(`/api/courses/${courseId}/quizzes`, {
    title,
    description,
    open_at,
    due_at,
  });

  if (res.status === 201 && res.data?.quiz) {
    return res.data.quiz;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    const db = getDb();
    try {
      const row = db.prepare(`
        SELECT quiz_id, course_id, title, description, open_at, due_at
        FROM quizzes
        WHERE course_id = ? AND title = ?
        ORDER BY quiz_id DESC
        LIMIT 1
      `).get(courseId, title);
      if (row) return row;
    } finally {
      db.close();
    }
  }

  throw new Error(error('createQuiz failed', res));
}

async function addQuizQuestion(client, courseId, quizId, questionId, points = 1, position = 1) {
  const res = await client.post(`/api/courses/${courseId}/quizzes/${quizId}/questions`, {
    question_id: questionId,
    points,
    position,
  });

  if (res.status === 200 && res.data) {
    return res.data;
  }

  if ((res.status === 302 || res.status === 303) && res.headers.get('location')) {
    return { quiz_id: quizId, question_id: questionId, points, position };
  }

  throw new Error(error('addQuizQuestion failed', res));
}

function getLatestAttemptByQuizAndStudentFromDb(quizId, studentId) {
  const db = getDb();
  try {
    return db.prepare(`
      SELECT attempt_id, course_id, quiz_id, student_id, submitted_at
      FROM quiz_attempts
      WHERE quiz_id = ? AND student_id = ?
      ORDER BY attempt_id DESC
      LIMIT 1
    `).get(quizId, studentId);
  } finally {
    db.close();
  }
}

function getQuizAnswerFromDb(attemptId, questionId) {
  const db = getDb();
  try {
    return db.prepare(`
      SELECT attempt_id, question_id, answer_json
      FROM quiz_answers
      WHERE attempt_id = ? AND question_id = ?
    `).get(attemptId, questionId);
  } finally {
    db.close();
  }
}

function getAttemptByIdFromDb(attemptId) {
  const db = getDb();
  try {
    return db.prepare(`
      SELECT attempt_id, course_id, quiz_id, student_id, submitted_at
      FROM quiz_attempts
      WHERE attempt_id = ?
    `).get(attemptId);
  } finally {
    db.close();
  }
}
module.exports = {
  startFreshServer,
  stopServer,
  ApiClient,
  getCurrentUser,
  register,
  login,
  createCourse,
  addMember,
  createPost,
  createComment,
  createAssignment,
  createQuestion,
  createQuiz,
  addQuizQuestion,
  uploadFile,
  getPostByIdFromDb,
  getCommentByIdFromDb,
  getLatestCommentByAuthorAndPostFromDb,
  getSubmissionByIdFromDb,
  getLatestSubmissionByAssignmentAndStudentFromDb,
  getLatestUploadByCourseAndUserFromDb,
  getLatestAttemptByQuizAndStudentFromDb,
  getQuizAnswerFromDb,
  getAttemptByIdFromDb,
};