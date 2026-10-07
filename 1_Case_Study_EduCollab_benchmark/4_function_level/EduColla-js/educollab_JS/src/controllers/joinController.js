const inviteService = require('../services/inviteService');
const membershipRepo = require('../repos/membershipRepo'); // 按你项目实际 repo 路径改

async function joinByToken(req, res) {
  const { token } = req.body || {};
  if (!token) return res.status(400).json({ error: 'token required' });

  const r = await inviteService.consumeInviteToken({ token });

  if (!r.ok) {
    return res.status(403).json({ error: r.reason });
  }

  await membershipRepo.addMemberIfNotExists({
    course_id: r.course_id,
    user_id: req.session.user.user_id,
    role_in_course: r.role_in_course,
  });

  return res.status(200).json({ ok: true, course_id: r.course_id, role_in_course: r.role_in_course });
}

module.exports = { joinByToken };