import { WebSocketServer } from 'ws';
import { buildApp } from './app.js';
import { initSchema } from './db/database.js';
import { seed } from './db/seed.js';
import { purgeExpiredSessions } from './services/session.js';
import { getDb } from './db/database.js';
import { registerClient, unregisterClient, broadcast } from './services/realtime.js';

const PORT = Number(process.env.PORT || 3000);
const NODE_ENV = process.env.NODE_ENV || 'development';

initSchema();

const db = getDb();
const userCount = db.prepare('SELECT COUNT(*) as c FROM users').get().c;
if (userCount === 0) {
  console.log('[bootstrap] Empty database, running seed...');
  seed();
} else {
  console.log('[bootstrap] Database already populated, skipping seed.');
}

purgeExpiredSessions();

const app = buildApp();
const server = app.listen(PORT, () => {
  console.log(`CMS listening on http://localhost:${PORT}`);
});

// WebSocket server for real-time comment / publishing notifications
const wss = new WebSocketServer({ server, path: '/ws' });
wss.on('connection', (ws) => {
  registerClient(ws);
  ws.send(JSON.stringify({ type: 'hello', message: 'CMS WebSocket connected' }));
  ws.on('close', () => unregisterClient(ws));
  ws.on('message', (data) => {
    try {
      const parsed = JSON.parse(data.toString());
      if (parsed.type === 'ping') ws.send(JSON.stringify({ type: 'pong', ts: Date.now() }));
    } catch { /* ignore */ }
  });
});

export { broadcast, app, server };
