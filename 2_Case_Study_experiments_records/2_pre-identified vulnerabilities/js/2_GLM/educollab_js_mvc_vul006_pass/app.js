const path = require('path');
const express = require('express');
const session = require('express-session');
const methodOverride = require('method-override');

const { initDb, seedAdmin } = require('./src/db');
const webRoutes = require('./src/routes/web');
const apiRoutes = require('./src/routes/api');

const app = express();

// --- Bootstrapping (FR-A1/FR-A2)
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
app.listen(PORT, () => {
  console.log(`EduCollab JS MVC v0 running on http://localhost:${PORT}`);
});
