const inviteRepo = require('../repos/inviteTokenRepo');
const membershipRepo = require('../repos/membershipRepo');
const { wantsJson, ok, fail } = require('./helpers');

function _baseUrl(req) {
  const proto = req.headers['x-forwarded-proto'] || req.protocol;
  return `${proto}://${req.get('host')}`;
}

function createCourseInvite(req, res) {
  const course_id = Number(req.params.course_id);
  const me = req.session.user;

  const role_in_course = (req.body && req.body.role_in_course) || 'student';
  const ttl_minutes = Number((req.body && req.body.ttl_minutes) || 60);

  const { token } = inviteRepo.createInvite({ course_id, role_in_course, created_by: me.user_id, ttl_minutes });

  const baseUrl = _baseUrl(req);
  const invite_link = `${baseUrl}/join?token=${encodeURIComponent(token)}`;

  return res.status(200).json({ invite_link });
}
// Any logged-in user can join a course using token (single-use + expiry)
function joinWithToken(req, res) {
  const me = req.session.user;
  const token = (req.query && req.query.token) || (req.body && req.body.token);
  if (!token) {
    if (wantsJson(req)) return fail(res, 400, 'Missing token');
    return res.status(400).send('Missing token');
  }
  const inv = inviteRepo.getValidByToken(token);

  if (!inv) {
    if (wantsJson(req)) return fail(res, 400, 'Invalid or expired token');
    return res.status(400).render('403'); // reuse 403 page for simplicity
  }

  // Ensure membership exists; if already a member, keep role as-is.
  const existing = membershipRepo.getByCourseAndUser(inv.course_id, me.user_id);
  if (!existing) {
    membershipRepo.addMember({ course_id: inv.course_id, user_id: me.user_id, role_in_course: inv.role_in_course });
  }

  inviteRepo.markUsed(inv.invite_id, me.user_id);

  if (wantsJson(req)) return ok(res, { joined: true, course_id: inv.course_id });

  return res.redirect(`/courses/${inv.course_id}`);
}

module.exports = { createCourseInvite, joinWithToken };
