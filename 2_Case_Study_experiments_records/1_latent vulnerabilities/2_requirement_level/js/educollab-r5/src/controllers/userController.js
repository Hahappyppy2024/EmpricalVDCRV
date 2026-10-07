const userRepository = require('../repositories/userRepository');

function listUsersPage(req, res) {
  res.render('admin_users', { users: userRepository.listUsers() });
}

function listUsersApi(req, res) {
  res.json({ success: true, users: userRepository.listUsers() });
}

module.exports = { listUsersPage, listUsersApi };
