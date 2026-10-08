import express from 'express';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import apiRoutes from './routes/api.js';
import pageRoutes from './routes/pages.js';
import { loadSession } from './middleware/auth.js';
import { notFoundHandler, errorHandler } from './middleware/errors.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export function createApp() {
  const app = express();

  app.set('view engine', 'ejs');
  app.set('views', path.join(__dirname, '..', 'views'));

  app.use(express.json({ limit: '1mb' }));
  app.use(express.urlencoded({ extended: true }));
  app.use(express.static(path.join(__dirname, '..', 'public')));

  app.use(loadSession);

  app.use('/api', apiRoutes);
  app.use('/', pageRoutes);

  app.use(notFoundHandler);
  app.use(errorHandler);

  return app;
}
