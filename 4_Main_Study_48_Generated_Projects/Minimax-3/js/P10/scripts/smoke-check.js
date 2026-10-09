#!/usr/bin/env node
// Smoke-check the running server without requiring a test framework.
import http from 'node:http';
import { URL } from 'node:url';

const BASE = process.env.SMOKE_BASE_URL || 'http://127.0.0.1:3000';
const TIMEOUT_MS = 4000;

function request(path, { method = 'GET', headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const url = new URL(BASE + path);
    const req = http.request({
      method, hostname: url.hostname, port: url.port, path: url.pathname + url.search,
      headers: { 'Accept': 'application/json', ...headers }
    }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        resolve({ status: res.statusCode, headers: res.headers, body: text });
      });
    });
    req.on('error', reject);
    req.setTimeout(TIMEOUT_MS, () => req.destroy(new Error('timeout')));
    if (body) req.write(body);
    req.end();
  });
}

function parseCookie(setCookie) {
  if (!setCookie) return null;
  const first = Array.isArray(setCookie) ? setCookie[0] : setCookie;
  return first ? first.split(';')[0] : null;
}

async function expect(name, fn) {
  process.stdout.write(` • ${name} … `);
  try {
    await fn();
    console.log('OK');
    return true;
  } catch (err) {
    console.log('FAIL');
    console.error('   reason:', err.message);
    return false;
  }
}

async function main() {
  console.log(`[smoke] target ${BASE}`);
  let allOk = true;
  allOk &= await expect('GET /healthz returns ok', async () => {
    const res = await request('/healthz');
    if (res.status !== 200) throw new Error('status ' + res.status);
    if (!/\"ok\"\s*:\s*true/.test(res.body)) throw new Error('body not ok');
  });
  let cookie = null;
  allOk &= await expect('POST /api/ai/auth/sign-in alice', async () => {
    const body = JSON.stringify({ email: 'alice@local.test', password: 'User#12345' });
    const res = await request('/api/ai/auth/sign-in', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) },
      body
    });
    if (res.status !== 200) throw new Error('status ' + res.status);
    cookie = parseCookie(res.headers['set-cookie']);
    if (!cookie) throw new Error('no session cookie');
  });
  allOk &= await expect('GET /api/ai/conversation_management is private', async () => {
    const res = await request('/api/ai/conversation_management', { headers: { Cookie: cookie } });
    if (res.status !== 200) throw new Error('status ' + res.status);
    if (!/"items"/.test(res.body)) throw new Error('no items');
  });
  allOk &= await expect('POST /api/ai/chat_execution runs deterministic prompt', async () => {
    // Pick the first conversation from the list endpoint to feed into the chat.
    const list = await request('/api/ai/conversation_management',
      { headers: { Cookie: cookie } });
    const parsed = JSON.parse(list.body);
    const convId = parsed.data.items[0].id;
    const body = JSON.stringify({ conversation_id: convId, prompt: 'What is RAG?' });
    const res = await request('/api/ai/chat_execution', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body), Cookie: cookie },
      body
    });
    if (res.status !== 201) throw new Error('status ' + res.status);
    const data = JSON.parse(res.body);
    if (!data.data.response) throw new Error('no response text');
  });
  allOk &= await expect('GET /api/ai/prompt_templates lists shared template', async () => {
    const res = await request('/api/ai/prompt_templates', { headers: { Cookie: cookie } });
    if (res.status !== 200) throw new Error('status ' + res.status);
    if (!/"Summarize meeting"/.test(res.body)) throw new Error('missing seed template');
  });
  allOk &= await expect('POST /api/ai/admin_moderation_and_settings as user → 403', async () => {
    const body = JSON.stringify({ key: 'demo', value: '1' });
    const res = await request('/api/ai/admin/settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body), Cookie: cookie },
      body
    });
    if (res.status !== 403) throw new Error('expected 403 got ' + res.status);
  });

  if (!allOk) {
    console.error('[smoke] FAILED');
    process.exit(1);
  }
  console.log('[smoke] all checks passed');
}

main().catch((err) => {
  console.error('[smoke] crashed', err);
  process.exit(1);
});
