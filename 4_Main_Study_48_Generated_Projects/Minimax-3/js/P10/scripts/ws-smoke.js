#!/usr/bin/env node
// Verifies the /ws/chat WebSocket streaming endpoint.
import { WebSocket } from 'ws';
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

(async () => {
  const body = JSON.stringify({ email: 'alice@local.test', password: 'User#12345' });
  const signin = await request('/api/ai/auth/sign-in', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) },
    body
  });
  if (signin.status !== 200) { console.error('sign-in failed', signin.status); process.exit(1); }
  const cookie = parseCookie(signin.headers['set-cookie']);
  const list = await request('/api/ai/conversation_management', { headers: { Cookie: cookie } });
  const parsed = JSON.parse(list.body);
  const conversationId = parsed.data.items[0].id;

  const url = new URL(BASE + '/ws/chat');
  url.protocol = url.protocol === 'https:' ? 'wss:' : 'ws:';
  const ws = new WebSocket(url.toString(), {
    headers: { Cookie: cookie }
  });
  const events = [];
  let buffer = '';
  ws.on('open', () => {
    ws.send(JSON.stringify({ conversation_id: conversationId, prompt: 'Hello world' }));
  });
  ws.on('message', (raw) => {
    const msg = JSON.parse(raw.toString('utf8'));
    events.push(msg.event);
    if (msg.event === 'token') buffer += msg.delta;
    if (msg.event === 'done') {
      console.log('[ws-smoke] events:', events.join(', '));
      console.log('[ws-smoke] received text:', buffer.slice(0, 80));
      ws.close();
      if (!events.includes('start') || !events.includes('done')) {
        console.error('[ws-smoke] missing events');
        process.exit(1);
      }
      console.log('[ws-smoke] OK');
    }
  });
  ws.on('error', (err) => {
    console.error('[ws-smoke] error', err);
    process.exit(1);
  });
})();
