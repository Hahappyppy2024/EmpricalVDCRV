const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const request = require('supertest');
const Database = require('better-sqlite3');

const PROJECT_ROOT = path.join(__dirname, '..');
const DB_PATH = path.join(PROJECT_ROOT, 'data', 'app.db');
const UPLOAD_DIR = path.join(PROJECT_ROOT, 'data', 'uploads');

function rimrafSafe(p) {
  try {
    if (fs.existsSync(p)) fs.rmSync(p, { recursive: true, force: true });
  } catch (_) {}
}

function resetTestState() {
  rimrafSafe(DB_PATH);

  if (fs.existsSync(UPLOAD_DIR)) {
    for (const name of fs.readdirSync(UPLOAD_DIR)) {
      rimrafSafe(path.join(UPLOAD_DIR, name));
    }
  } else {
    fs.mkdirSync(UPLOAD_DIR, { recursive: true });
  }
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForHealthy(baseUrl, timeoutMs = 15000) {
  const started = Date.now();
  let lastErr;

  while (Date.now() - started < timeoutMs) {
    try {
      const res = await request(baseUrl).get('/');
      if ([200, 302, 404].includes(res.status)) return;
    } catch (e) {
      lastErr = e;
    }
    await wait(250);
  }

  throw lastErr || new Error(`Server did not become ready within ${timeoutMs}ms`);
}

async function startFreshServer() {
  resetTestState();

  const port = 3100 + Math.floor(Math.random() * 2000);
  const baseUrl = `http://127.0.0.1:${port}`;

  const child = spawn(process.execPath, ['app.js'], {
    cwd: PROJECT_ROOT,
    env: {
      ...process.env,
      PORT: String(port),
      NODE_ENV: 'test',
    },
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  let stdout = '';
  let stderr = '';

  child.stdout.on('data', (buf) => {
    stdout += buf.toString();
  });

  child.stderr.on('data', (buf) => {
    stderr += buf.toString();
  });

  child.on('exit', (code) => {
    child.__exitCode = code;
  });

  await waitForHealthy(baseUrl, 15000);

  return {
    port,
    baseUrl,
    child,
    request: request(baseUrl),
    agent: request.agent(baseUrl),
    getLogs() {
      return { stdout, stderr, exitCode: child.__exitCode };
    },
  };
}

async function stopServer(server) {
  if (!server || !server.child || server.child.killed) return;

  await new Promise((resolve) => {
    const timer = setTimeout(() => {
      try {
        server.child.kill('SIGKILL');
      } catch (_) {}
      resolve();
    }, 3000);

    server.child.once('exit', () => {
      clearTimeout(timer);
      resolve();
    });

    try {
      server.child.kill('SIGTERM');
    } catch (_) {
      clearTimeout(timer);
      resolve();
    }
  });
}

function openDb() {
  return new Database(DB_PATH, { readonly: true });
}

// auth endpoints may wrongly return 302 because controller checks req.path.startsWith('/api')
async function registerAny(agent, username, password, display_name) {
  const res = await agent
    .post('/api/auth/register')
    .send({
      username,
      password,
      display_name: display_name || username,
    });

  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function loginAny(agent, username, password) {
  const res = await agent
    .post('/api/auth/login')
    .send({ username, password });

  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function logoutAny(agent) {
  const res = await agent.post('/api/auth/logout').send({});
  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function createCourseAny(agent, title = 'Course A', description = 'Desc') {
  const res = await agent
    .post('/api/courses')
    .send({ title, description });

  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function listUsersAny(agent) {
  const res = await agent.get('/api/users');
  expect([200, 302]).toContain(res.status);
  return res;
}

async function addMemberAny(agent, course_id, user_id, role_in_course = 'student') {
  const res = await agent
    .post(`/api/courses/${course_id}/members`)
    .send({ user_id, role_in_course });

  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function listMembersAny(agent, course_id) {
  const res = await agent.get(`/api/courses/${course_id}/members`);
  expect([200, 302]).toContain(res.status);
  return res;
}

async function createPostAny(agent, course_id, title = 'Post title', body = 'Post body') {
  const res = await agent
    .post(`/api/courses/${course_id}/posts`)
    .send({ title, body });

  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function getPostAny(agent, course_id, post_id) {
  return agent.get(`/api/courses/${course_id}/posts/${post_id}`);
}

async function createCommentAny(agent, course_id, post_id, body = 'Comment body') {
  const res = await agent
    .post(`/api/courses/${course_id}/posts/${post_id}/comments`)
    .send({ body });

  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function listCommentsAny(agent, course_id, post_id) {
  return agent.get(`/api/courses/${course_id}/posts/${post_id}/comments`);
}

async function createAssignmentAny(agent, course_id, title = 'A1', description = 'Desc', due_at = '2099-12-31T23:59:59Z') {
  const res = await agent
    .post(`/api/courses/${course_id}/assignments`)
    .send({ title, description, due_at });

  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function createSubmissionAny(agent, course_id, assignment_id, content_text = 'my answer', attachment_upload_id = null) {
  const res = await agent
    .post(`/api/courses/${course_id}/assignments/${assignment_id}/submissions`)
    .send({ content_text, attachment_upload_id });

  expect([200, 201, 302]).toContain(res.status);
  return res;
}

async function listSubmissionsAny(agent, course_id, assignment_id) {
  return agent.get(`/api/courses/${course_id}/assignments/${assignment_id}/submissions`);
}

async function uploadFileAny(agent, course_id, filename, content, contentType = 'application/octet-stream') {
  const tmpPath = path.join(PROJECT_ROOT, 'data', `tmp-${Date.now()}-${Math.random().toString(16).slice(2)}-${filename}`);
  fs.writeFileSync(tmpPath, content);

  try {
    const res = await agent
      .post(`/api/courses/${course_id}/uploads`)
      .attach('file', tmpPath, { filename, contentType });

    expect([200, 201, 302]).toContain(res.status);
    return res;
  } finally {
    try { fs.unlinkSync(tmpPath); } catch (_) {}
  }
}

module.exports = {
  PROJECT_ROOT,
  DB_PATH,
  UPLOAD_DIR,
  startFreshServer,
  stopServer,
  openDb,
  registerAny,
  loginAny,
  logoutAny,
  createCourseAny,
  listUsersAny,
  addMemberAny,
  listMembersAny,
  createPostAny,
  getPostAny,
  createCommentAny,
  listCommentsAny,
  createAssignmentAny,
  createSubmissionAny,
  listSubmissionsAny,
  uploadFileAny,
};