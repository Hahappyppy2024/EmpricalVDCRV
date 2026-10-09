#!/usr/bin/env node
// Extra end-to-end checks beyond smoke-check.js to validate the use cases.
import http from 'node:http';
import { URL } from 'node:url';

const BASE = process.env.SMOKE_BASE_URL || 'http://127.0.0.1:3000';

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
    if (body) req.write(body);
    req.end();
  });
}

function parseCookie(setCookie) {
  if (!setCookie) return null;
  const first = Array.isArray(setCookie) ? setCookie[0] : setCookie;
  return first ? first.split(';')[0] : null;
}

let allOk = true;
async function expect(name, fn) {
  process.stdout.write(` • ${name} … `);
  try { await fn(); console.log('OK'); }
  catch (err) { console.log('FAIL'); console.error('  ', err.message); allOk = false; }
}

(async () => {
  console.log(`[extra-smoke] target ${BASE}`);

  // Admin can read settings; users cannot (already in smoke-check but re-checked)
  const adminBody = JSON.stringify({ email: 'admin@local.test', password: 'Admin#12345' });
  const adminRes = await request('/api/ai/auth/sign-in', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(adminBody) },
    body: adminBody
  });
  const adminCookie = parseCookie(adminRes.headers['set-cookie']);
  if (!adminCookie) { console.error('no admin cookie'); process.exit(1); }

  await expect('Admin can list settings', async () => {
    const res = await request('/api/ai/admin_moderation_and_settings',
      { headers: { Cookie: adminCookie } });
    if (res.status !== 200) throw new Error('status ' + res.status);
    const json = JSON.parse(res.body);
    if (!Array.isArray(json.data.settings)) throw new Error('settings missing');
  });

  await expect('Admin can suspend and reinstate Bob', async () => {
    const list = await request('/api/ai/admin/users', { headers: { Cookie: adminCookie } });
    const parsed = JSON.parse(list.body);
    const bob = parsed.data.items.find(u => u.email === 'bob@local.test');
    if (!bob) throw new Error('bob not found');
    const body1 = JSON.stringify({ user_id: bob.id, decision: 'suspend', reason: 'demo' });
    const r1 = await request('/api/ai/admin/moderation', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body1), Cookie: adminCookie }, body: body1 });
    if (r1.status !== 200) throw new Error('suspend failed: ' + r1.status);
    const body2 = JSON.stringify({ user_id: bob.id, decision: 'reinstate', reason: 'demo end' });
    const r2 = await request('/api/ai/admin/moderation', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body2), Cookie: adminCookie }, body: body2 });
    if (r2.status !== 200) throw new Error('reinstate failed: ' + r2.status);
  });

  await expect('Alice can upload knowledge, attach to collection, and search', async () => {
    const aliceBody = JSON.stringify({ email: 'alice@local.test', password: 'User#12345' });
    const aliceRes = await request('/api/ai/auth/sign-in', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(aliceBody) }, body: aliceBody });
    const aliceCookie = parseCookie(aliceRes.headers['set-cookie']);
    if (!aliceCookie) throw new Error('no alice cookie');

    const fileBuf = Buffer.from('RAG combines retrieval with generation.\nIt cites sources.');
    const boundary = '----bench' + Math.random().toString(16).slice(2);
    const multipart = Buffer.concat([
      Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="file"; filename="kb.txt"\r\nContent-Type: text/plain\r\n\r\n`),
      fileBuf,
      Buffer.from(`\r\n--${boundary}--\r\n`)
    ]);
    const uploadRes = await request('/api/ai/knowledge_file_upload', {
      method: 'POST',
      headers: { 'Content-Type': `multipart/form-data; boundary=${boundary}`,
        'Content-Length': multipart.length, Cookie: aliceCookie },
      body: multipart
    });
    if (uploadRes.status !== 201) throw new Error('upload failed: ' + uploadRes.status + ' ' + uploadRes.body);
    const uploaded = JSON.parse(uploadRes.body).data;

    // Attach to an existing collection named 'RAG notes'.
    const cols = await request('/api/ai/retrieval_collection',
      { headers: { Cookie: aliceCookie } });
    const parsedCols = JSON.parse(cols.body);
    const rag = parsedCols.data.items.find(c => c.name === 'RAG notes');
    if (!rag) throw new Error('RAG notes collection missing');
    const attachBody = JSON.stringify({ file_id: uploaded.id });
    const attachRes = await request(`/api/ai/retrieval_collection/${rag.id}/files`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(attachBody), Cookie: aliceCookie },
      body: attachBody });
    if (attachRes.status !== 201) throw new Error('attach failed: ' + attachRes.status);

    const searchBody = JSON.stringify({ query: 'retrieval', limit: 3 });
    const searchRes = await request(`/api/ai/retrieval_collection/${rag.id}/search`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(searchBody), Cookie: aliceCookie },
      body: searchBody });
    if (searchRes.status !== 200) throw new Error('search failed: ' + searchRes.status);
    const result = JSON.parse(searchRes.body);
    if (!result.data.results.length) throw new Error('no chunks returned');
  });

  await expect('Public share link renders without authentication', async () => {
    const aliceBody = JSON.stringify({ email: 'alice@local.test', password: 'User#12345' });
    const aliceRes = await request('/api/ai/auth/sign-in', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(aliceBody) }, body: aliceBody });
    const aliceCookie = parseCookie(aliceRes.headers['set-cookie']);
    // Pick Alice's first conversation.
    const list = await request('/api/ai/conversation_management',
      { headers: { Cookie: aliceCookie } });
    const parsed = JSON.parse(list.body);
    const convId = parsed.data.items[0].id;
    const shareBody = JSON.stringify({ conversation_id: convId, ttl_seconds: 600 });
    const shareRes = await request('/api/ai/share_conversation', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(shareBody), Cookie: aliceCookie },
      body: shareBody });
    if (shareRes.status !== 201) throw new Error('share create failed: ' + shareRes.status);
    const token = JSON.parse(shareRes.body).data.token;
    const viewRes = await request('/api/ai/public/share/' + token);
    if (viewRes.status !== 200) throw new Error('public view failed: ' + viewRes.status);
    const viewJson = JSON.parse(viewRes.body);
    if (!viewJson.data.messages.length) throw new Error('no messages in share');
  });

  if (!allOk) { console.error('[extra-smoke] FAILED'); process.exit(1); }
  console.log('[extra-smoke] all checks passed');
})();
