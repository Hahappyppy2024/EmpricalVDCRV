import http from 'node:http';
import env from './config/env.js';
import { getDb } from './db/index.js';
import { createApp, startScheduledPublishing } from './app.js';
import { initRealTime } from './services/realTime.js';

const app = createApp();
const server = http.createServer(app);

initRealTime(server);
startScheduledPublishing();

server.listen(env.port, env.host, () => {
  const db = getDb();
  const counts = {
    users: db.prepare('SELECT COUNT(*) AS c FROM users').get().c,
    articles: db.prepare('SELECT COUNT(*) AS c FROM articles').get().c
  };
  // eslint-disable-next-line no-console
  console.log(`[server] P05 Content Management System running at http://${env.host}:${env.port}`);
  console.log(`[server] Seeded users: ${counts.users}, articles: ${counts.articles}`);
  console.log(`[server] WebSocket endpoint: ws://${env.host}:${env.port}/ws`);
});

function shutdown() {
  // eslint-disable-next-line no-console
  console.log('[server] Shutting down...');
  try {
    server.close();
  } catch {
    // ignore
  }
  process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
