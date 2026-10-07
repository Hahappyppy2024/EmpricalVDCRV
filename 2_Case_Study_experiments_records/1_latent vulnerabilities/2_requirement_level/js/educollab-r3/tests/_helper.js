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

/**
 * Black-box server start:
 * - copies the repo root (one level up from tests/)
 * - starts `node app.js` with PORT
 */
async function startFreshServer() {
  const sourceDir = path.resolve(__dirname, '..');
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'educollab-api-'));
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

  async request(method, pathname, options = {}) {
    const headers = {};
    let body;
    let json;

    if (
      options &&
      typeof options === 'object' &&
      !Array.isArray(options) &&
      Object.prototype.hasOwnProperty.call(options, 'json')
    ) {
      json = options.json;
    } else if (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
      json = options;
    }

    if (json !== undefined && method !== 'GET' && method !== 'HEAD') {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(json);
    }

    if (this.cookie) {
      headers['Cookie'] = this.cookie;
    }

    const res = await fetch(`${this.baseUrl}${pathname}`, {
      method,
      headers,
      body,
      redirect: 'manual'
    });

    this._storeCookies(res);

    const text = await res.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch (_) {
      data = text;
    }

    return { status: res.status, headers: res.headers, data, text };
  }

  get(pathname, options) {
    return this.request('GET', pathname, options);
  }

  post(pathname, options) {
    return this.request('POST', pathname, options);
  }

  put(pathname, options) {
    return this.request('PUT', pathname, options);
  }

  delete(pathname, options) {
    return this.request('DELETE', pathname, options);
  }
}

async function registerAndLogin(
  client,
  username,
  displayName = username,
  password = 'pass123'
) {
  const res = await client.post('/api/auth/register', {
    json: {
      username,
      password,
      display_name: displayName
    }
  });

  assert.equal(
    res.status,
    201,
    `register failed for ${username}: ${JSON.stringify(res.data)}`
  );

  return res.data.user;
}

module.exports = {
  assert,
  startFreshServer,
  stopServer,
  ApiClient,
  registerAndLogin
};