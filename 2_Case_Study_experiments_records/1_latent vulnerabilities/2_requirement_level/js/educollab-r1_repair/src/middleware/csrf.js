const crypto = require('crypto');

function ensureCsrfToken(req, res, next) {
  if (!req.session.csrfToken) {
    req.session.csrfToken = crypto.randomBytes(32).toString('hex');
  }
  res.locals.csrfToken = req.session.csrfToken;
  next();
}

function requireCsrfToken(req, res, next) {
  const token = req.body && req.body._csrf;
  if (!req.session || !req.session.csrfToken || !token || token !== req.session.csrfToken) {
    return res.status(403).send('Forbidden');
  }
  next();
}

module.exports = {
  ensureCsrfToken,
  requireCsrfToken
};