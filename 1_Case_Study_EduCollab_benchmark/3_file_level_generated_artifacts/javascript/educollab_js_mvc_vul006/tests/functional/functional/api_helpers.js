const fs = require('fs');
const path = require('path');
const net = require('net');
const { spawn } = require('child_process');
const Database = require('better-sqlite3');

const PROJECT_ROOT = path.resolve(__dirname, '..', '..');
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

async function waitForServer(baseUrl, timeoutMs = 10000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    try {
      const res = await fetch(`${baseUrl}/api/courses`);
      if ([200, 401, 403].includes(res.status)) return;
    } catch (_) {}
    await new Promise((r) => setTimeout(r, 200));
  }
  throw new Error(`Server did not become ready within ${timeoutMs}ms`);
}

async function startFreshServer() {
  resetTestState();

  const port = await getFreePort();
  const baseUrl = `http://127.0.0.1:${port}`;

  const proc = spawn(process.execPath, ['app.js'], {
    cwd: PROJECT_ROOT,
    env: { ...process.env, PORT: String(port) },
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  let stdout = '';
  let stderr = '';

  proc.stdout.on('data', (chunk) => {
    stdout += chunk.toString();
  });
  proc.stderr.on('data', (chunk) => {
    stderr += chunk.toString();
  });

  await waitForServer(baseUrl);

  return {
    proc,
    port,
    baseUrl,
    stdout: () => stdout,
    stderr: () => stderr,
  };
}

async function stopServer(server) {
  if (!server || !server.proc || server.proc.killed) return;

  await new Promise((resolve) => {
    const done = () => resolve();
    server.proc.once('exit', done);
    server.proc.kill('SIGTERM');

    setTimeout(() => {
      if (!server.proc.killed) {
        try {
          server.proc.kill('SIGKILL');
        } catch (_) {}
      }
      resolve();
    }, 3000);
  });
}

function openDb() {
  return new Database(DB_PATH, { readonly: true });
}

class ApiClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this.cookie = '';
  }

  _storeCookie(res) {
    const raw = res.headers.get('set-cookie');
    if (!raw) return;
    const first = raw.split(',')[0];
    this.cookie = first.split(';')[0];
  }

  async request(method, urlPath, { json, body, headers } = {}) {
    const finalHeaders = { ...(headers || {}) };

    if (this.cookie) {
      finalHeaders.cookie = this.cookie;
    }

    let finalBody = body;
    if (json !== undefined) {
      finalHeaders['content-type'] = 'application/json';
      finalBody = JSON.stringify(json);
    }

    const res = await fetch(`${this.baseUrl}${urlPath}`, {
      method,
      headers: finalHeaders,
      body: finalBody,
      redirect: 'manual',
    });

    this._storeCookie(res);

    const contentType = res.headers.get('content-type') || '';
    let data = null;

    if (contentType.includes('application/json')) {
      data = await res.json();
    } else {
      data = await res.text();
    }

    return { status: res.status, data, headers: res.headers };
  }

  get(path) {
    return this.request('GET', path);
  }

  post(path, payload) {
    return this.request('POST', path, payload);
  }

  put(path, payload) {
    return this.request('PUT', path, payload);
  }

  delete(path, payload) {
    return this.request('DELETE', path, payload);
  }
}

module.exports = {
  startFreshServer,
  stopServer,
  openDb,
  ApiClient,
};