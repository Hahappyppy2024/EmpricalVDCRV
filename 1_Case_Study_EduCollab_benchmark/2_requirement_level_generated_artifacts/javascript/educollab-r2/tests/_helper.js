const fs = require('fs');
const path = require('path');
const os = require('os');
const net = require('net');
const { spawn } = require('child_process');
const Database = require('better-sqlite3');

let currentAppDir = null;

function projectRoot() {
  return path.resolve(__dirname, '..');
}

function copyDir(src, dest) {
  fs.cpSync(src, dest, { recursive: true });
}

function dbPath(baseDir = currentAppDir || projectRoot()) {
  return path.join(baseDir, 'data', 'app.db');
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function getFreePort() {
  return await new Promise((resolve, reject) => {
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
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    try {
      const res = await fetch(baseUrl + '/', { redirect: 'manual' });
      if (res.status >= 200 && res.status < 500) return;
    } catch (_) {
      // keep waiting
    }
    await sleep(200);
  }
  throw new Error(`Server did not become ready within ${timeoutMs}ms`);
}

async function startFreshServer() {
  const sourceRoot = projectRoot();

  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'educollab-r2-'));
  const appDir = path.join(tempRoot, 'app');
  copyDir(sourceRoot, appDir);

  const dataDir = path.join(appDir, 'data');
  fs.mkdirSync(dataDir, { recursive: true });

  const tempDb = dbPath(appDir);
  if (fs.existsSync(tempDb)) {
    fs.unlinkSync(tempDb);
  }

  currentAppDir = appDir;

  const port = await getFreePort();
  const child = spawn(process.execPath, ['app.js'], {
    cwd: appDir,
    env: { ...process.env, PORT: String(port) },
    stdio: ['ignore', 'pipe', 'pipe']
  });

  let stdout = '';
  let stderr = '';

  child.stdout.on('data', (buf) => {
    stdout += buf.toString();
  });

  child.stderr.on('data', (buf) => {
    stderr += buf.toString();
  });

  const baseUrl = `http://127.0.0.1:${port}`;
  try {
    await waitForServer(baseUrl);
  } catch (err) {
    child.kill('SIGTERM');
    throw new Error(
      err.message +
        (stdout ? `\nServer stdout:\n${stdout}` : '') +
        (stderr ? `\nServer stderr:\n${stderr}` : '')
    );
  }

  return {
    child,
    baseUrl,
    appDir,
    stdout: () => stdout,
    stderr: () => stderr
  };
}

async function stopServer(server) {
  if (!server || !server.child || server.child.killed) return;

  await new Promise((resolve) => {
    let done = false;

    const finish = () => {
      if (done) return;
      done = true;
      resolve();
    };

    server.child.once('exit', finish);
    server.child.kill('SIGTERM');

    setTimeout(() => {
      try {
        if (!server.child.killed) {
          server.child.kill('SIGKILL');
        }
      } catch (_) {
        // ignore
      }
      finish();
    }, 1500);
  });
}

function openDb() {
  if (!currentAppDir) {
    throw new Error('openDb() called before startFreshServer()');
  }
  return new Database(dbPath(currentAppDir));
}

class ApiClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this.cookies = new Map();
  }

  _storeCookies(response) {
    const setCookies =
      typeof response.headers.getSetCookie === 'function'
        ? response.headers.getSetCookie()
        : (response.headers.get('set-cookie')
            ? [response.headers.get('set-cookie')]
            : []);

    for (const raw of setCookies) {
      const first = raw.split(';')[0];
      const idx = first.indexOf('=');
      if (idx > 0) {
        const key = first.slice(0, idx).trim();
        const value = first.slice(idx + 1).trim();
        this.cookies.set(key, value);
      }
    }
  }

  _cookieHeader() {
    return Array.from(this.cookies.entries())
      .map(([k, v]) => `${k}=${v}`)
      .join('; ');
  }

  async request(method, urlPath, { json, headers } = {}) {
    const finalHeaders = { ...(headers || {}) };

    if (this.cookies.size > 0) {
      finalHeaders.cookie = this._cookieHeader();
    }

    let body;
    if (json !== undefined) {
      finalHeaders['content-type'] = 'application/json';
      body = JSON.stringify(json);
    }

    const response = await fetch(this.baseUrl + urlPath, {
      method,
      headers: finalHeaders,
      body,
      redirect: 'manual'
    });

    this._storeCookies(response);

    const contentType = response.headers.get('content-type') || '';
    let data;
    if (contentType.includes('application/json')) {
      data = await response.json();
    } else {
      data = await response.text();
    }

    return { status: response.status, headers: response.headers, data };
  }

  get(urlPath, options) {
    return this.request('GET', urlPath, options);
  }

  post(urlPath, options) {
    return this.request('POST', urlPath, options);
  }

  put(urlPath, options) {
    return this.request('PUT', urlPath, options);
  }

  delete(urlPath, options) {
    return this.request('DELETE', urlPath, options);
  }
}

module.exports = {
  startFreshServer,
  stopServer,
  openDb,
  ApiClient,
};