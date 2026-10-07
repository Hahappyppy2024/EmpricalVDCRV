const bcrypt = require('bcryptjs');
const userRepo = require('../repos/userRepo');
const auditRepo = require('../repos/auditRepo');

const isApi = (req) => (req.originalUrl || '').startsWith('/api');

function safeIp(req) {
  return req.ip || null;
}

function safeUa(req) {
  return (req.get && req.get('user-agent')) ? req.get('user-agent') : null;
}

function auditSafeCreate(payload) {
  try {
    auditRepo.create(payload);
  } catch (e) {
    // logging failure must never break auth flow
  }
}


function registerForm(req, res) {
  return res.render('auth/register');
}

function register(req, res) {
  const { username, password, display_name } = req.body || {};
  if (!username || !password || !display_name) {
    if (isApi(req)) return res.status(400).json({ error: 'username, password, display_name required' });
    return res.status(400).render('auth/register', { error: 'All fields required' });
  }
  const existing = userRepo.getAuthByUsername(username);
  if (existing) {
    if (isApi(req)) return res.status(400).json({ error: 'username already exists' });
    return res.status(400).render('auth/register', { error: 'Username already exists' });
  }

  const password_hash = bcrypt.hashSync(password, 10);
  const user = userRepo.createUser({ username, password_hash, display_name });


  req.session.user = { user_id: user.user_id, username: user.username, display_name: user.display_name };

  auditSafeCreate({
    actor_user_id: user.user_id,
    actor_username: user.username,
    action: 'register',
    target_type: 'user',
    target_id: user.user_id,
    meta_json: { via: isApi(req) ? 'api' : 'web', ip: safeIp(req), ua: safeUa(req) }
  });

  if (isApi(req)) return res.json({ user: req.session.user });
  return res.redirect('/me');
}

function loginForm(req, res) {
  return res.render('auth/login');
}

function login(req, res) {
  const { username, password } = req.body || {};
  if (!username || !password) {
    if (isApi(req)) return res.status(400).json({ error: 'username and password required' });
    return res.status(400).render('auth/login', { error: 'Username and password required' });
  }

  const u = userRepo.getAuthByUsername(username);
  const ok = !!u && bcrypt.compareSync(password, u.password_hash);

  if (!ok) {
    auditSafeCreate({
      actor_user_id: null,
      actor_username: String(username),
      action: 'login_failed',               /
      target_type: 'user',
      target_id: u?.user_id ?? null,
      meta_json: {
        via: isApi(req) ? 'api' : 'web',
        ip: safeIp(req),
        ua: safeUa(req)
      }
    });

    if (isApi(req)) return res.status(401).json({ error: 'Invalid credentials' });
    return res.status(401).render('auth/login', { error: 'Invalid credentials' });
  }

  req.session.user = { user_id: u.user_id, username: u.username, display_name: u.display_name };

  auditSafeCreate({
    actor_user_id: u.user_id,
    actor_username: u.username,
    action: 'login_success',
    target_type: 'user',
    target_id: u.user_id,
    meta_json: {
      via: isApi(req) ? 'api' : 'web',
      ip: safeIp(req),
      ua: safeUa(req)
    }
  });

  if (isApi(req)) return res.json({ user: req.session.user });
  return res.redirect('/me');
}


function logout(req, res) {
  const me = req.session?.user || null;
  if (me) {
    auditSafeCreate({
      actor_user_id: me.user_id ?? null,
      actor_username: me.username ?? null,
      action: 'logout',
      target_type: 'user',
      target_id: me.user_id ?? null,
      meta_json: { via: isApi(req) ? 'api' : 'web', ip: safeIp(req), ua: safeUa(req) }
    });
  }

  req.session.destroy(() => {
    if (isApi(req)) return res.json({ ok: true });
    return res.redirect('/');
  });
}


function me(req, res) {
  if (isApi(req)) return res.json({ user: req.session.user });
  return res.render('auth/me');
}

module.exports = { registerForm, register, loginForm, login, logout, me };