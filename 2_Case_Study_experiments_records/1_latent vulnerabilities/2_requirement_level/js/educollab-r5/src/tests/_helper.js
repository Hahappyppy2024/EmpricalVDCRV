const path = require('path');
const fs = require('fs');
const os = require('os');
const net = require('net');
const assert = require('node:assert/strict');
const { spawn } = require('child_process');

function copyDir(src, dest) {
  fs.cpSync(src, dest, { recursive: true });
}

function getFreePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      server.close(() => resolve(port));
    });
    server.on('error', reject);
  });
}

async function waitForServer(baseUrl, timeoutMs = 12000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    try {
      const res = await fetch(`${baseUrl}/`);
      if (res.status >= 200 && res.status < 500) return;
    } catch (_) {}
    await new Promise(r => setTimeout(r, 150));
  }
  throw new Error(`Server did not become ready: ${baseUrl}`);
}

async function startFreshServer() {
  // Expect tests/ to be placed in the project root (same level as app.js).
  const sourceDir = path.resolve(__dirname, '..', '..');
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'educollab-r5-'));
  const appDir = path.join(tempRoot, 'app');
  copyDir(sourceDir, appDir);

  const port = await getFreePort();
  const proc = spawn(process.execPath, ['app.js'], {
    cwd: appDir,
    env: { ...process.env, PORT: String(port) },
    stdio: ['ignore', 'pipe', 'pipe']
  });

  let stderr = '';
  proc.stderr.on('data', chunk => { stderr += chunk.toString(); });

  const baseUrl = `http://127.0.0.1:${port}`;
  try {
    await waitForServer(baseUrl);
  } catch (err) {
    proc.kill('SIGTERM');
    throw new Error(`${err.message}\n${stderr}`);
  }

  return { proc, appDir, baseUrl };
}

async function stopServer(server) {
  if (!server || !server.proc || server.proc.killed) return;
  server.proc.kill('SIGTERM');
  await new Promise(resolve => server.proc.once('exit', resolve));
}

class ApiClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this.cookie = '';
  }

  _storeCookies(res) {
    const header = res.headers.get('set-cookie');
    if (!header) return;
    // take first cookie in header (undici may fold multiple cookies)
    const first = header.split(/,(?=\s*[^;]+=[^;]+)/)[0];
    this.cookie = first.split(';')[0];
  }

  async request(method, pathname, body, headers = {}) {
    const hdrs = { ...headers };
    if (this.cookie) hdrs['Cookie'] = this.cookie;

    const res = await fetch(`${this.baseUrl}${pathname}`, {
      method,
      headers: hdrs,
      body,
      redirect: 'manual'
    });

    this._storeCookies(res);

    const buf = Buffer.from(await res.arrayBuffer());
    const text = buf.toString('utf8');
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch (_) {
      data = text;
    }

    return { status: res.status, headers: res.headers, data, text, raw: buf };
  }

  get(pathname) { return this.request('GET', pathname); }
  delete(pathname) { return this.request('DELETE', pathname); }

  postJson(pathname, json) {
    return this.request('POST', pathname, JSON.stringify(json), { 'Content-Type': 'application/json' });
  }

  putJson(pathname, json) {
    return this.request('PUT', pathname, JSON.stringify(json), { 'Content-Type': 'application/json' });
  }
}

async function login(client, username, password) {
  const res = await client.postJson('/api/auth/login', { username, password });
  assert.equal(res.status, 200, `login failed: ${JSON.stringify(res.data)}`);
  return res.data.user;
}

async function register(client, username, password = 'pass123', display_name = username) {
  const res = await client.postJson('/api/auth/register', { username, password, display_name });
  assert.equal(res.status, 201, `register failed: ${JSON.stringify(res.data)}`);
  return res.data.user;
}

async function createCourse(client, title = 'Course A', description = 'D') {
  const res = await client.postJson('/api/courses', { title, description });
  assert.equal(res.status, 201, `create course failed: ${JSON.stringify(res.data)}`);
  return res.data.course;
}

async function addMember(staffClient, courseId, userId, role) {
  const res = await staffClient.postJson(`/api/courses/${courseId}/members`, { user_id: userId, role_in_course: role });
  assert.equal(res.status, 201, `add member failed: ${JSON.stringify(res.data)}`);
  return res.data.membership;
}

async function createQuestion(staffClient, courseId, { qtype = 'single_choice', prompt = '2+2=?', options = ['3','4','5'], answer = { correct: '4' } } = {}) {
  const res = await staffClient.postJson(`/api/courses/${courseId}/questions`, {
    qtype,
    prompt,
    options_json: JSON.stringify(options),
    answer_json: JSON.stringify(answer)
  });
  assert.equal(res.status, 201, `create question failed: ${JSON.stringify(res.data)}`);
  return res.data.question;
}

async function createQuiz(staffClient, courseId, { title = 'Quiz 1', description = 'Q', open_at = null, due_at = null } = {}) {
  const res = await staffClient.postJson(`/api/courses/${courseId}/quizzes`, { title, description, open_at, due_at });
  assert.equal(res.status, 201, `create quiz failed: ${JSON.stringify(res.data)}`);
  return res.data.quiz;
}

async function addQuizQuestion(staffClient, courseId, quizId, questionId, points = 1, position = 1) {
  const res = await staffClient.postJson(`/api/courses/${courseId}/quizzes/${quizId}/questions`, {
    question_id: questionId,
    points,
    position
  });
  assert.equal(res.status, 201, `add quiz question failed: ${JSON.stringify(res.data)}`);
  return res.data.quiz_questions;
}

module.exports = {
  assert,
  fs,
  path,
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
};
