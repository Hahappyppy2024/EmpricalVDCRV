import express from 'express';
import cookieParser from 'cookie-parser';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { render } from './renderer.js';
import { attachSession } from './middleware/auth.js';
import { errorHandler, notFoundHandler } from './middleware/error.js';
import accountRouter from './routes/account.js';
import articlesRouter from './routes/articles.js';
import richTextRouter from './routes/richtext.js';
import mediaRouter, { UPLOAD_DIR } from './routes/media.js';
import publishingRouter from './routes/publishing.js';
import publicRouter from './routes/public.js';
import commentsRouter from './routes/comments.js';
import templatesRouter from './routes/templates.js';
import usersRouter from './routes/users.js';
import settingsRouter from './routes/settings.js';
import importExportRouter from './routes/importexport.js';
import frontendRouter from './routes/frontend.js';
import { getDb } from './db/database.js';

const __dirname = dirname(fileURLToPath(import.meta.url));

export function buildApp() {
  const app = express();
  app.disable('x-powered-by');

  app.use(express.json({ limit: '2mb' }));
  app.use(express.urlencoded({ extended: true }));
  app.use(cookieParser());
  app.use(attachSession);

  // Static files
  app.use('/css', express.static(join(__dirname, '..', 'public', 'css')));
  app.use('/js', express.static(join(__dirname, '..', 'public', 'js')));
  app.use('/uploads', express.static(UPLOAD_DIR));

  // Render helper exposed to res.render
  app.use((req, res, next) => {
    res.render = (view, params = {}) => {
      const ctx = { user: req.user, ...params };
      const content = params.content !== undefined ? params.content : render(view, ctx);
      const html = render('layout', { ...ctx, content });
      res.set('Content-Type', 'text/html; charset=utf-8');
      res.send(html);
    };
    next();
  });

  // Page routes (HTML)
  app.get('/', (req, res) => res.render('home', { title: 'Home', pageId: 'home', pageJs: 'home' }));
  app.get('/login', (req, res) => {
    if (req.user) return res.redirect('/dashboard');
    res.render('login', { title: 'Sign in', pageId: 'login', pageJs: 'login' });
  });
  app.get('/register', (req, res) => {
    if (req.user) return res.redirect('/dashboard');
    res.render('register', { title: 'Register', pageId: 'register', pageJs: 'register' });
  });

  // Dashboard with role-based cards
  app.get('/dashboard', (req, res) => {
    if (!req.user) return res.redirect('/login');
    const cards = [
      { title: 'Articles', href: '/articles', description: 'Browse and manage articles.' }
    ];
    if (['admin', 'editor', 'author'].includes(req.user.role_name)) cards.push({ title: 'New article', href: '/articles/new', description: 'Create a new draft.' });
    if (['admin', 'editor', 'author'].includes(req.user.role_name)) cards.push({ title: 'Media library', href: '/media', description: 'Upload and manage media files.' });
    if (['admin', 'editor'].includes(req.user.role_name)) cards.push({ title: 'Publishing workflow', href: '/workflow', description: 'Move articles through workflow.' });
    if (['admin', 'editor'].includes(req.user.role_name)) cards.push({ title: 'Templates', href: '/templates', description: 'Page templates and menus.' });
    if (req.user.role_name === 'moderator' || req.user.role_name === 'admin' || req.user.role_name === 'editor') cards.push({ title: 'Comments', href: '/comments', description: 'Moderate visitor comments.' });
    if (req.user.role_name === 'admin') {
      cards.push({ title: 'Users & roles', href: '/users', description: 'Manage users, roles, permissions.' });
      cards.push({ title: 'Settings', href: '/settings', description: 'Plugin and site settings.' });
      cards.push({ title: 'Import / Export', href: '/importexport', description: 'Import or export site data.' });
    }
    cards.push({ title: 'Frontend API tools', href: '/frontend', description: 'Trigger API states for testing.' });
    res.render('dashboard', { title: 'Dashboard', pageId: 'dashboard', pageJs: 'dashboard', dashboardCards: cards });
  });

  app.get('/articles', (req, res) => res.render('articles', { title: 'Articles', pageId: 'articles', pageJs: 'articles' }));
  app.get('/articles/new', (req, res) => {
    if (!req.user) return res.redirect('/login');
    if (!['admin', 'editor', 'author'].includes(req.user.role_name)) return res.status(403).send('forbidden');
    const db = getDb();
    const categories = db.prepare('SELECT id, name FROM categories ORDER BY name').all();
    const templates = db.prepare('SELECT id, name FROM page_templates ORDER BY name').all();
    res.render('article_form', { title: 'New article', pageId: 'article_form', pageJs: 'article_form', modeLabel: 'New article', submitLabel: 'Create draft', categories, templates });
  });
  app.get('/articles/edit/:id', (req, res) => {
    if (!req.user) return res.redirect('/login');
    const id = Number(req.params.id);
    if (Number.isNaN(id)) return res.redirect('/articles');
    const db = getDb();
    const categories = db.prepare('SELECT id, name FROM categories ORDER BY name').all();
    const templates = db.prepare('SELECT id, name FROM page_templates ORDER BY name').all();
    res.render('article_form', { title: 'Edit article', pageId: 'article_form', pageJs: 'article_form', modeLabel: 'Edit article', submitLabel: 'Save changes', categories, templates });
  });

  app.get('/media', (req, res) => {
    if (!req.user) return res.redirect('/login');
    res.render('media', { title: 'Media', pageId: 'media', pageJs: 'media' });
  });
  app.get('/workflow', (req, res) => {
    if (!req.user) return res.redirect('/login');
    if (!['admin', 'editor'].includes(req.user.role_name)) return res.status(403).send('forbidden');
    res.render('workflow', { title: 'Publishing workflow', pageId: 'workflow', pageJs: 'workflow' });
  });
  app.get('/comments', (req, res) => {
    if (!req.user) return res.redirect('/login');
    if (!['admin', 'editor', 'moderator'].includes(req.user.role_name)) return res.status(403).send('forbidden');
    res.render('comments', { title: 'Comments moderation', pageId: 'comments', pageJs: 'comments' });
  });
  app.get('/templates', (req, res) => {
    if (!req.user) return res.redirect('/login');
    if (!['admin', 'editor'].includes(req.user.role_name)) return res.status(403).send('forbidden');
    res.render('templates', { title: 'Templates', pageId: 'templates', pageJs: 'templates' });
  });
  app.get('/users', (req, res) => {
    if (!req.user) return res.redirect('/login');
    if (req.user.role_name !== 'admin') return res.status(403).send('forbidden');
    res.render('users', { title: 'Users & roles', pageId: 'users', pageJs: 'users' });
  });
  app.get('/settings', (req, res) => {
    if (!req.user) return res.redirect('/login');
    if (req.user.role_name !== 'admin') return res.status(403).send('forbidden');
    res.render('settings', { title: 'Settings', pageId: 'settings', pageJs: 'settings' });
  });
  app.get('/importexport', (req, res) => {
    if (!req.user) return res.redirect('/login');
    if (req.user.role_name !== 'admin') return res.status(403).send('forbidden');
    res.render('importexport', { title: 'Import / Export', pageId: 'importexport', pageJs: 'importexport' });
  });
  app.get('/frontend', (req, res) => {
    if (!req.user) return res.redirect('/login');
    res.render('frontend', { title: 'Frontend API tools', pageId: 'frontend', pageJs: 'frontend' });
  });

  // Public site pages
  app.get('/articles-list', (req, res) => res.render('public_articles', { title: 'Articles', pageId: 'public_articles', pageJs: 'public_articles' }));
  app.get('/public/:slug', (req, res) => res.render('article', { title: 'Article', pageId: 'article', pageJs: 'article' }));

  // API routes (all under /api/cms)
  const api = express.Router();
  api.use('/cms', accountRouter);
  api.use('/cms', articlesRouter);
  api.use('/cms', richTextRouter);
  api.use('/cms', mediaRouter);
  api.use('/cms', publishingRouter);
  api.use('/cms', publicRouter);
  api.use('/cms', commentsRouter);
  api.use('/cms', templatesRouter);
  api.use('/cms', usersRouter);
  api.use('/cms', settingsRouter);
  api.use('/cms', importExportRouter);
  api.use('/cms', frontendRouter);
  app.use('/api', api);

  app.use(notFoundHandler);
  app.use(errorHandler);

  return app;
}
