import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const viewsDir = path.resolve(__dirname, '..', 'views');

const PUBLIC_PAGES = ['/', '/search', '/category/:slug'];
const APP_PAGES = [
  '/dashboard',
  '/content',
  '/content/new',
  '/content/edit/:id',
  '/media',
  '/publishing',
  '/templates',
  '/admin/users',
  '/admin/settings',
  '/admin/import-export',
  '/diagnostics'
];

function send(viewFile) {
  return (_req, res) => res.sendFile(path.join(viewsDir, viewFile));
}

export function registerPageRoutes(app) {
  app.get('/', send('home.html'));
  app.get('/search', send('home.html'));
  app.get('/category/:slug', send('home.html'));
  app.get('/article/:slug', send('article.html'));
  app.get('/login', send('login.html'));
  app.get('/signup', send('login.html'));
  app.get('/page/:slug', send('page.html'));

  for (const route of APP_PAGES) {
    app.get(route, send('app.html'));
  }
}
