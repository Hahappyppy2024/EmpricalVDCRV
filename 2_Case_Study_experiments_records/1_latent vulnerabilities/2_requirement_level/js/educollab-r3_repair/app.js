const path = require('path');
const express = require('express');
const session = require('express-session');

const { initDb, seedAdmin } = require('./scripts/initDb');
const webRoutes = require('./src/routes/web');
const apiRoutes = require('./src/routes/api');
const { attachCurrentUser } = require('./src/middleware/auth');

initDb();
seedAdmin();

const app = express();

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'src', 'views'));

app.use(express.urlencoded({ extended: true }));
app.use(express.json());

app.use(
  session({
    secret: process.env.SESSION_SECRET || 'educollab-dev-secret',
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true
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
