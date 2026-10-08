import path from 'node:path';
import express from 'express';
import { ROOT_DIR, config } from './config.js';
import { notFound, errorHandler } from './middleware/error.js';
import { sessionFromDb } from './middleware/auth.js';
import { authRouter } from './routes/auth.js';
import { modulesRouter } from './routes/modules.js';
import { shareRouter } from './routes/share.js';

function cookieParser(req, res, next) {
  req.cookies = {};
  const header = req.headers.cookie;
  if (header) {
    for (const part of header.split(';')) {
      const idx = part.indexOf('=');
      if (idx > 0) {
        const key = part.slice(0, idx).trim();
        const value = decodeURIComponent(part.slice(idx + 1).trim());
        req.cookies[key] = value;
      }
    }
  }
  next();
}

export function createApp({ db, audit, usage, settings, chat, files, broadcast }) {
  const app = express();
  app.disable('x-powered-by');
  app.use(cookieParser);
  app.use(express.json({ limit: '2mb' }));
  app.use(express.static(path.join(ROOT_DIR, 'public')));
  app.use(sessionFromDb(db));

  app.get('/api/health', (req, res) => {
    res.json({ ok: true, service: 'p10-ai-assistant-llm-webui', time: new Date().toISOString() });
  });

  app.use('/api/auth', authRouter(db, { audit, settings }));
  app.use('/api/ai', modulesRouter(db, { audit, usage, settings, chat, files, broadcast }));
  app.use('/api/share', shareRouter(db));

  app.get('/share/:token', (req, res) => {
    res.sendFile(path.join(ROOT_DIR, 'public', 'share.html'));
  });

  app.get('/app', (req, res) => {
    res.sendFile(path.join(ROOT_DIR, 'public', 'app.html'));
  });

  app.get('/auth', (req, res) => {
    res.sendFile(path.join(ROOT_DIR, 'public', 'auth.html'));
  });

  app.get('/', (req, res) => {
    res.redirect(req.user ? '/app' : '/auth');
  });

  app.use(notFound);
  app.use(errorHandler);

  return app;
}
