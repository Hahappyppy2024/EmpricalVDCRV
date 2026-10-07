const userRepository = require('../repositories/userRepository');

function attachCurrentUser(req, res, next) {
  const userId = req.session.userId;
  if (userId) {
    req.currentUser = userRepository.findById(userId) || null;
  } else {
    req.currentUser = null;
  }
  next();
}

function requireAuthPage(req, res, next) {
  if (!req.currentUser) {
    return res.redirect('/login');
  }
  next();
}

function requireAuthApi(req, res, next) {
  if (!req.currentUser) {
    return res.status(401).json({ success: false, error: 'Authentication required' });
  }
  next();
}

function requireAdminPage(req, res, next) {
  if (!req.currentUser || !req.currentUser.is_admin) {
    return res.status(403).send('Forbidden');
  }
  next();
}

function requireAdminApi(req, res, next) {
  if (!req.currentUser || !req.currentUser.is_admin) {
    return res.status(403).json({ success: false, error: 'Admin access required' });
  }
  next();
}

module.exports = {
  attachCurrentUser,
  requireAuthPage,
  requireAuthApi,
  requireAdminPage,
  requireAdminApi
};