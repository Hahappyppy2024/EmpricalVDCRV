const bcrypt = require('bcryptjs');
const userRepo = require('../repos/userRepo');

// FR-U1 register_student
function registerForm(req, res) {
  res.render('auth/register');
}

function register(req, res) {
  const { username, password, display_name } = req.body;
  if (!username || !password || !display_name) {
    if (req.path.startsWith('/api')) return res.status(400).json({ error: 'username, password, display_name required' });
    return res.status(400).render('auth/register', { error: 'All fields required' });
  }
  const existing = userRepo.getAuthByUsername(username);
  if (existing) {
    if (req.path.startsWith('/api')) return res.status(400).json({ error: 'username already exists' });
    return res.status(400).render('auth/register', { error: 'Username already exists' });
  }
  const password_hash = bcrypt.hashSync(password, 10);
  const user = userRepo.createUser({ username, password_hash, display_name });

  // student registers -> logged in
  req.session.user = { user_id: user.user_id, username: user.username, display_name: user.display_name };
  if (req.path.startsWith('/api')) return res.json({ user: req.session.user });
  return res.redirect('/me');
}

// FR-U2 login
function loginForm(req, res) {
  res.render('auth/login');
}

function login(req, res) {
  const { username, password } = req.body;
  if (!username || !password) {
    if (req.path.startsWith('/api')) return res.status(400).json({ error: 'username and password required' });
    return res.status(400).render('auth/login', { error: 'Username and password required' });
  }
  const u = userRepo.getAuthByUsername(username);
  if (!u || !bcrypt.compareSync(password, u.password_hash)) {
    if (req.path.startsWith('/api')) return res.status(401).json({ error: 'Invalid credentials' });
    return res.status(401).render('auth/login', { error: 'Invalid credentials' });
  }
  req.session.user = { user_id: u.user_id, username: u.username, display_name: u.display_name };
  if (req.path.startsWith('/api')) return res.json({ user: req.session.user });
  return res.redirect('/me');
}

// FR-U3 logout
function logout(req, res) {
  req.session.destroy(() => {
    if (req.path.startsWith('/api')) return res.json({ ok: true });
    return res.redirect('/');
  });
}

// FR-U4 me
function me(req, res) {
  if (req.path.startsWith('/api')) return res.json({ user: req.session.user });
  res.render('auth/me');
}

module.exports = { registerForm, register, loginForm, login, logout, me };
