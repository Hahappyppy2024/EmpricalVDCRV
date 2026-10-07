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
      const address = server.address();
      const port = address && address.port;
      server.close(() => resolve(port));
    });
    server.on('error', reject);
  });
}

async function waitForServer(baseUrl, timeoutMs = 10000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    try {
      const res = await fetch(`${baseUrl}/`, { redirect: 'manual' });
      if (res.status >= 200 && res.status < 500) return;
    } catch (_) {
      // keep waiting
    }
    await new Promise((resolve) => setTimeout(resolve, 150));
  }
  throw new Error(`Server did not become ready: ${baseUrl}`);
}

async function startFreshServer() {
  const sourceDir = path.resolve(__dirname, '..');
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'educollab-r4-'));
  const appDir = path.join(tempRoot, 'app');
  copyDir(sourceDir, appDir);

  const port = await getFreePort();
  const proc = spawn(process.execPath, ['app.js'], {
    cwd: appDir,
    env: { ...process.env, PORT: String(port) },
    stdio: ['ignore', 'pipe', 'pipe']
  });

  let stdout = '';
  let stderr = '';

  proc.stdout.on('data', (chunk) => {
    stdout += chunk.toString();
  });

  proc.stderr.on('data', (chunk) => {
    stderr += chunk.toString();
  });

  const baseUrl = `http://127.0.0.1:${port}`;
  try {
    await waitForServer(baseUrl);
  } catch (err) {
    proc.kill('SIGTERM');
    throw new Error(
      `${err.message}\n\nSTDOUT:\n${stdout}\n\nSTDERR:\n${stderr}`
    );
  }

  return { proc, appDir, baseUrl };
}

async function stopServer(server) {
  if (!server || !server.proc || server.proc.killed) return;

  await new Promise((resolve) => {
    let finished = false;

    const done = () => {
      if (finished) return;
      finished = true;
      resolve();
    };

    server.proc.once('exit', done);
    server.proc.kill('SIGTERM');

    setTimeout(() => {
      try {
        if (!server.proc.killed) {
          server.proc.kill('SIGKILL');
        }
      } catch (_) {
        // ignore
      }
      done();
    }, 1500);
  });
}

class ApiClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this.cookie = '';
  }

  _storeCookies(res) {
    const header = res.headers.get('set-cookie');
    if (!header) return;
    const first = header.split(/,(?=\s*[^;]+=[^;]+)/)[0];
    this.cookie = first.split(';')[0];
  }

  async request(method, pathname, body, headers = {}) {
    const hdrs = { ...headers };
    if (this.cookie) hdrs['Cookie'] = this.cookie;

    const fetchOptions = {
      method,
      headers: hdrs,
      redirect: 'manual'
    };

    if (body !== undefined && method !== 'GET' && method !== 'HEAD') {
      fetchOptions.body = body;
    }

    const res = await fetch(`${this.baseUrl}${pathname}`, fetchOptions);

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

  get(pathname) {
    return this.request('GET', pathname);
  }

  delete(pathname) {
    return this.request('DELETE', pathname);
  }

  postJson(pathname, json) {
    return this.request(
      'POST',
      pathname,
      JSON.stringify(json),
      { 'Content-Type': 'application/json' }
    );
  }

  putJson(pathname, json) {
    return this.request(
      'PUT',
      pathname,
      JSON.stringify(json),
      { 'Content-Type': 'application/json' }
    );
  }

  postForm(pathname, formData) {
    return this.request('POST', pathname, formData);
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
  const res = await staffClient.postJson(
    `/api/courses/${courseId}/members`,
    { user_id: userId, role_in_course: role }
  );
  assert.equal(res.status, 201, `add member failed: ${JSON.stringify(res.data)}`);
  return res.data.membership;
}

async function createAssignment(staffClient, courseId, title = 'HW1', description = 'Do it', due_at = null) {
  const res = await staffClient.postJson(
    `/api/courses/${courseId}/assignments`,
    { title, description, due_at }
  );
  assert.equal(res.status, 201, `create assignment failed: ${JSON.stringify(res.data)}`);
  return res.data.assignment;
}

async function createSubmission(studentClient, courseId, assignmentId, content_text = 'answer', attachment_upload_id = null) {
  const res = await studentClient.postJson(
    `/api/courses/${courseId}/assignments/${assignmentId}/submissions`,
    { content_text, attachment_upload_id }
  );
  assert.equal(res.status, 201, `create submission failed: ${JSON.stringify(res.data)}`);
  return res.data.submission;
}

function uploadsDir(appDir) {
  return path.join(appDir, 'storage', 'uploads');
}

function walkFiles(rootDir) {
  const out = [];

  function walk(p) {
    const st = fs.statSync(p);
    if (st.isDirectory()) {
      for (const name of fs.readdirSync(p)) {
        walk(path.join(p, name));
      }
    } else {
      out.push(p);
    }
  }

  if (fs.existsSync(rootDir)) walk(rootDir);
  return out;
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
  createAssignment,
  createSubmission,
  uploadsDir,
  walkFiles
};