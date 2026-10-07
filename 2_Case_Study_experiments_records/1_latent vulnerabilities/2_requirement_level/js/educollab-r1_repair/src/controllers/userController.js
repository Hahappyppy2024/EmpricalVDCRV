const userRepository = require('../repositories/userRepository');

function listUsersPage(req, res) {
  const users = userRepository.listUsers();
  res.render('admin_users', { users });
}

function listUsersApi(req, res) {
  const users = userRepository.listUsers();
  res.json({ success: true, users });
}

module.exports = {
  listUsersPage,
  listUsersApi
};
