const userRepo = require('../repos/userRepo');

function listUsers(req, res) {
  const me = req.session.user;
  if (!me || me.username !== 'admin') {
    if (req.path.startsWith('/api')) return res.status(403).json({ error: 'Forbidden' });
    return res.status(403).render('403');
  }

  const users = userRepo.listUsers();
  if (req.path.startsWith('/api')) return res.json({ users });
  return res.render('admin/users', { users });
}

module.exports = { listUsers };