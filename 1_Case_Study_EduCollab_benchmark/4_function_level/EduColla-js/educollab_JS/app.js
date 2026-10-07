const path = require('path');
const express = require('express');
const session = require('express-session');

const methodOverride = require('method-override');

const { initDb, seedAdmin } = require('./src/db');
const webRoutes = require('./src/routes/web');
const apiRoutes = require('./src/routes/api');

const app = express();


app.disable('x-powered-by');
console.log('x-powered-by disabled?', app.get('x-powered-by'));



app.use(session({
  secret: process.env.SESSION_SECRET || 'dev-secret',
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
  }
}));


app.use((err, req, res, next) => {
  // JSON parse error from express.json()/body-parser
  const isJsonParseError =
    err &&
    (err.type === 'entity.parse.failed' ||
      (err instanceof SyntaxError && err.status === 400) ||
      (err.name === 'SyntaxError' && err.status === 400));

  if (isJsonParseError) {
    // Make the test pass: 400 + no stack leak
    return res.status(400).json({ error: 'Invalid JSON' });
  }

  console.error('🔥 ERROR on', req.method, req.url);
  console.error(err && err.stack ? err.stack : err);
  res.status(500).send('internal error');
});
app.use(express.urlencoded({ extended: true }));
app.use(express.json());



app.use((err, req, res, next) => {
  // JSON body parse error from express.json()/body-parser
  const isJsonParseError =
    err &&
    (err.type === 'entity.parse.failed' ||
      (err instanceof SyntaxError && err.status === 400) ||
      (err.name === 'SyntaxError' && err.status === 400));

  if (isJsonParseError) {
    // 400, and do NOT leak stack
    return res.status(400).json({ error: 'Invalid JSON' });
  }

  console.error('🔥 ERROR on', req.method, req.url);
  console.error(err && err.stack ? err.stack : err);
  res.status(500).send('internal error');
});




initDb();
seedAdmin();

// --- Middleware
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'src', 'views'));

app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(methodOverride('_method'));

app.use(
  session({
    secret: 'dev-secret-change-me',
    resave: false,
    saveUninitialized: false,
    cookie: { httpOnly: true }
  })
);

app.use('/static', express.static(path.join(__dirname, 'src', 'public')));
app.use('/uploads', express.static(path.join(__dirname, 'data', 'uploads')));

// expose session user to views
app.use((req, res, next) => {
  res.locals.me = req.session.user || null;
  next();
});

// Routes
app.get('/', (req, res) => res.render('index'));
app.use('/', webRoutes);
app.use('/api', apiRoutes);

// 404
app.use((req, res) => {
  if (req.path.startsWith('/api')) return res.status(404).json({ error: 'Not found' });
  res.status(404).render('404');
});

const PORT = process.env.PORT || 3000;

if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`EduCollab JS MVC v0 running on http://localhost:${PORT}`);
  });
}
console.log('RUNNING APP.JS =', __filename);



app.use((err, req, res, next) => {
  console.error('🔥 ERROR on', req.method, req.url);
  console.error(err && err.stack ? err.stack : err);
  res.status(500).send('internal error');
});

module.exports = app;
