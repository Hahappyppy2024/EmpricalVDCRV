import http from 'node:http';
import { config } from './config.js';
import { openDatabase, closeDatabase } from './db/database.js';
import { seedDatabase } from './db/seed.js';
import { createAuditService } from './services/auditService.js';
import { createUsageService } from './services/usageService.js';
import { createSettingsService } from './services/settingsService.js';
import { createChatService } from './services/chatService.js';
import { createFileService } from './services/fileService.js';
import { attachRealtime } from './realtime/ws.js';
import { createApp } from './app.js';

const db = openDatabase();
seedDatabase(db, { force: false });

const settings = createSettingsService(db);
const audit = createAuditService(db);
const usage = createUsageService(db);
const chat = createChatService(db, settings);
const files = createFileService(db);

const server = http.createServer();
const realtime = attachRealtime(server, db);

const app = createApp({ db, audit, usage, settings, chat, files, broadcast: realtime.broadcast });
server.on('request', app);

server.listen(config.port, () => {
  console.log(`P10 AI Assistant / LLM WebUI listening on http://localhost:${config.port}`);
  console.log('Seed accounts: admin/admin123 (admin), alice/alice123 (user), bob/bob123 (user)');
});

function shutdown() {
  server.close(() => {
    closeDatabase(db);
    process.exit(0);
  });
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
