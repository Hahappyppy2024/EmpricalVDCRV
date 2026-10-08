// P10 realtime + page smoke test
import { WebSocket } from 'ws';

const BASE = 'http://localhost:3000';
let pass = 0;
let fail = 0;
const check = (label, cond, extra = '') => {
  if (cond) { pass += 1; console.log(`  PASS  ${label}`); }
  else { fail += 1; console.log(`  FAIL  ${label} ${extra}`); }
};

const login = async () => {
  const res = await fetch(BASE + '/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: 'alice', password: 'alice123' }),
  });
  return (res.headers.get('set-cookie') || '').split(';')[0];
};

const main = async () => {
  const root = await fetch(BASE + '/', { redirect: 'manual' });
  check('root serves redirect page', root.status === 200 || (root.status >= 300 && root.status < 400), `status=${root.status}`);

  const expected = {
    '/auth': 'text/html',
    '/app': 'text/html',
    '/share/share-prod-launch-3f7a': 'text/html',
    '/css/app.css': 'text/css',
    '/js/app.js': 'javascript',
  };
  for (const [path, type] of Object.entries(expected)) {
    const res = await fetch(BASE + path);
    const ct = res.headers.get('content-type') || '';
    check(`${path} served as ${type}`, res.status === 200 && ct.includes(type), `status=${res.status} ct=${ct}`);
  }

  const cookie = await login();
  const ws = new WebSocket(BASE.replace('http', 'ws') + '/ws', { headers: { Cookie: cookie } });
  const opened = await new Promise((resolve) => {
    ws.on('open', () => resolve(true));
    ws.on('error', (e) => { console.log('WS error', e.message); resolve(false); });
    setTimeout(() => resolve(false), 3000);
  });
  check('ws authenticated connect', opened === true);

  if (opened) {
    const messages = [];
    ws.on('message', (data) => messages.push(JSON.parse(data.toString())));
    await new Promise((r) => setTimeout(r, 300));
    check('ws welcome event', messages.some((m) => m.type === 'ws:connected'));

    const chatRes = await fetch(BASE + '/api/ai/chat_execution', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Cookie: cookie },
      body: JSON.stringify({ prompt: 'test realtime broadcast 123' }),
    });
    const chatBody = await chatRes.json();
    check('chat request ok', chatRes.status === 201 && chatBody.ok, `status=${chatRes.status} ${JSON.stringify(chatBody)}`);

    await new Promise((r) => setTimeout(r, 800));
    check(
      'ws chat:execution broadcast',
      messages.some((m) => m.type === 'chat:execution' && m.data.user_id === 2),
      `messages=${JSON.stringify(messages)}`
    );
    ws.close();
  }

  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
};
main().catch((e) => { console.error(e); process.exit(2); });
