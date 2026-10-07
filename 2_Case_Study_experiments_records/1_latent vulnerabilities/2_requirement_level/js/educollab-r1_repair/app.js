const path = require('path');
const crypto = require('crypto');
const express = require('express');
const session = require('express-session');

const { initDb, seedAdmin } = require('./scripts/initDb');
const webRoutes = require('./src/routes/web');
const apiRoutes = require('./src/routes/api');
const { attachCurrentUser } = require('./src/middleware/auth');

initDb();
seedAdmin();

const app = express();
const sessionSecret = process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex');

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'src', 'views'));

app.use(express.urlencoded({ extended: true }));
app.use(express.json());

app.use(
  session({
    secret: sessionSecret,
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      sameSite: 'lax'
    }
  })
);

app.use(attachCurrentUser);

app.use((req, res, next) => {
  res.locals.currentUser = req.currentUser || null;
  next();
});

app.use('/', webRoutes);
app.use('/api', apiRoutes);

app.use((req, res) => {
  if (req.path.startsWith('/api')) {
    return res.status(404).json({ success: false, error: 'Not found' });
  }
  return res.status(404).send('Not found');
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`EduCollab running on http://localhost:${PORT}`);
});