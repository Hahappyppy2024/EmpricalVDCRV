const userRepository = require('../repositories/userRepository');

function showRegister(req, res) {
  res.render('register', { error: null });
}

function register(req, res) {
  const { username, password, display_name } = req.body;

  if (!username || !password || !display_name) {
    return res.status(400).render('register', { error: 'All fields are required.' });
  }

  const existing = userRepository.findByUsername(username);
  if (existing) {
    return res.status(400).render('register', { error: 'Username already exists.' });
  }

  const user = userRepository.createUser({ username, password, display_name });
  req.session.userId = user.user_id;
  return res.redirect('/me');
}

function apiRegister(req, res) {
  const { username, password, display_name } = req.body;

  if (!username || !password || !display_name) {
    return res.status(400).json({ success: false, error: 'username, password, and display_name are required' });
  }

  const existing = userRepository.findByUsername(username);
  if (existing) {
    return res.status(400).json({ success: false, error: 'Username already exists' });
  }

  const user = userRepository.createUser({ username, password, display_name });
  req.session.userId = user.user_id;
  return res.status(201).json({ success: true, user });
}

function showLogin(req, res) {
  res.render('login', { error: null });
}

function login(req, res) {
  const { username, password } = req.body;
  const authUser = userRepository.findAuthByUsername(username);

  if (!authUser || !userRepository.verifyPassword(authUser, password)) {
    return res.status(401).render('login', { error: 'Invalid credentials.' });
  }

  req.session.userId = authUser.user_id;
  return res.redirect('/me');
}

function apiLogin(req, res) {
  const { username, password } = req.body;
  const authUser = userRepository.findAuthByUsername(username);

  if (!authUser || !userRepository.verifyPassword(authUser, password)) {
    return res.status(401).json({ success: false, error: 'Invalid credentials' });
  }

  req.session.userId = authUser.user_id;
  const user = userRepository.findById(authUser.user_id);
  return res.json({ success: true, user });
}

function logout(req, res) {
  req.session.destroy(() => {
    res.redirect('/login');
  });
}

function apiLogout(req, res) {
  req.session.destroy(() => {
    res.json({ success: true });
  });
}

function me(req, res) {
  if (!req.currentUser) {
    return res.redirect('/login');
  }
  return res.render('me', { user: req.currentUser });
}

function apiMe(req, res) {
  if (!req.currentUser) {
    return res.status(401).json({ success: false, error: 'Authentication required' });
  }
  return res.json({ success: true, user: req.currentUser });
}

module.exports = {
  showRegister,
  register,
  apiRegister,
  showLogin,
  login,
  apiLogin,
  logout,
  apiLogout,
  me,
  apiMe
};
