import http from 'node:http';
import { config } from './config.js';
import { createApp } from './app.js';
import { initDatabase, getDb, closeDatabase } from './db/database.js';
import { seedDatabase } from './db/seed.js';
import { attachWs } from './services/wsHub.js';

initDatabase();
const db = getDb();
const userCount = db.prepare('SELECT COUNT(*) AS n FROM users').get().n;
if (userCount === 0) {
  seedDatabase();
  console.log('[seed] database seeded with deterministic fixtures');
}

const app = createApp();
const server = http.createServer(app);
attachWs(server);

server.listen(config.port, config.host, () => {
  console.log(`[server] P04 Hotel Booking System running at http://${config.host}:${config.port}`);
  console.log(`[server] environment: ${config.env}`);
});

const shutdown = (signal) => {
  console.log(`[server] received ${signal}, shutting down`);
  server.close(() => {
    closeDatabase();
    process.exit(0);
  });
  setTimeout(() => process.exit(0), 3000).unref();
};

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
