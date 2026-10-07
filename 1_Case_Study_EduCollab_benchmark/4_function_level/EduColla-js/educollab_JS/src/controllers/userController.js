const userRepo = require('../repos/userRepo');

function listUsers(req, res) {
  const users = userRepo.listUsers();
  if (req.originalUrl.startsWith('/api')) return res.json({ users });
  return res.render('admin/users', { users });
}

module.exports = { listUsers };
