import express from 'express';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { config } from './config.js';
import { getDb } from './db/connection.js';
import { applySchema } from './db/schema.js';
import { seedAll } from './db/seed.js';
import { attachSession } from './middleware/auth.js';
import { errorHandler, notFoundHandler } from './middleware/error.js';

import accountRoutes from './routes/account.js';
import conversationRoutes from './routes/conversations.js';
import promptTemplateRoutes from './routes/prompt_templates.js';
import modelConfigRoutes from './routes/model_configuration.js';
import knowledgeRoutes from './routes/knowledge_files.js';
import retrievalRoutes from './routes/retrieval_collections.js';
import toolPluginRoutes from './routes/tool_plugins.js';
import apiKeyRoutes from './routes/api_keys.js';
import chatExecutionRoutes from './routes/chat_execution.js';
import shareRoutes from './routes/share_conversation.js';
import usageAuditRoutes from './routes/usage_audit.js';
import adminRoutes from './routes/admin.js';

import { attachWebSocketServer } from './ws/chat.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export function buildApp({ autoSeed = true } = {}) {
  // Ensure database + schema exist before handling any request.
  getDb();
  applySchema();
  if (autoSeed) {
    const db = getDb();
    const userCount = db.prepare(`SELECT COUNT(*) AS c FROM users`).get().c;
    if (userCount === 0) {
      seedAll();
      console.log('[boot] seeded default users + fixtures');
    }
  }

  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '2mb' }));
  app.use(express.urlencoded({ extended: false, limit: '2mb' }));
  app.use(attachSession);

  // Static browser client
  const publicDir = path.resolve(__dirname, '..', 'public');
  app.use(express.static(publicDir));

  // Mount all API routes under /api/ai (use case contract)
  app.use('/api/ai', accountRoutes);
  app.use('/api/ai', conversationRoutes);
  app.use('/api/ai', promptTemplateRoutes);
  app.use('/api/ai', modelConfigRoutes);
  app.use('/api/ai', knowledgeRoutes);
  app.use('/api/ai', retrievalRoutes);
  app.use('/api/ai', toolPluginRoutes);
  app.use('/api/ai', apiKeyRoutes);
  app.use('/api/ai', chatExecutionRoutes);
  app.use('/api/ai', shareRoutes);
  app.use('/api/ai', usageAuditRoutes);
  app.use('/api/ai', adminRoutes);

  // Public share viewer endpoint
  app.use('/api/ai', shareRoutes);

  // Lightweight health endpoint
  app.get('/healthz', (_req, res) => {
    res.json({ ok: true, data: { status: 'healthy', uptime: process.uptime() } });
  });

  app.get('/api/me', (req, res) => {
    if (!req.user) {
      res.status(401).json({ ok: false, error: { code: 'unauthorized', message: 'Not signed in' } });
      return;
    }
    res.json({ ok: true, data: { user: req.user, session_id: req.sessionId } });
  });

  app.use(notFoundHandler);
  app.use(errorHandler);

  return app;
}

export function startServer() {
  const app = buildApp();
  const httpServer = app.listen(config.port, config.host, () => {
    console.log(`[server] listening on http://${config.host}:${config.port}`);
    console.log(`[server] environment: ${config.nodeEnv}`);
    console.log(`[server] db path: ${path.resolve(process.cwd(), config.dbPath)}`);
  });
  attachWebSocketServer(httpServer);
  return httpServer;
}
