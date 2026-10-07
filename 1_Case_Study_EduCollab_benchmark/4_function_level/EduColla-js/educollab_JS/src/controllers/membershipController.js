const membershipRepo = require('../repos/membershipRepo');
const userRepo = require('../repos/userRepo');

function newMemberForm(req, res) {
  const course_id = Number(req.params.course_id);
  const users = userRepo.listUsers();
  res.render('members/new', { course_id, users, roles: ['admin', 'teacher', 'student', 'assistant', 'senior-assistant'] });
}

function addMember(req, res) {
  const course_id = Number(req.params.course_id);
  const { user_id, role_in_course } = req.body;
  try {
    const member = membershipRepo.addMember({ course_id, user_id: Number(user_id), role_in_course });
    if (req.originalUrl.startsWith('/api')) return res.status(201).json({ membership: member });
    return res.redirect(`/courses/${course_id}/members`);
  } catch (e) {
    if (req.originalUrl.startsWith('/api')) return res.status(400).json({ error: 'failed to add member (maybe already added)' });
    return res.status(400).render('members/new', { course_id, users: userRepo.listUsers(), roles: ['admin', 'teacher', 'student', 'assistant', 'senior-assistant'], error: 'Failed to add (maybe already a member)' });
  }
}

function listMembers(req, res) {
  const course_id = Number(req.params.course_id);
  const members = membershipRepo.listMembers(course_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ members });

  const me = req.session.user;
  const role = (req.courseMembership && req.courseMembership.role_in_course) || '';
  const can_generate_invite = me && me.username === 'admin' || ['teacher', 'assistant', 'senior-assistant'].includes(role);

  // invite link is passed back as a query param after generation
  const invite_link = req.query && req.query.invite ? String(req.query.invite) : null;

  res.render('members/list', {
    course_id,
    members,
    roles: ['admin', 'teacher', 'student', 'assistant', 'senior-assistant'],
    can_generate_invite,
    invite_link,
  });
}


function updateMemberRole(req, res) {
  const course_id = Number(req.params.course_id);
  const membership_id = Number(req.params.membership_id);
  const { role_in_course } = req.body;
  const updated = membershipRepo.updateRole(membership_id, role_in_course);
  if (req.originalUrl.startsWith('/api')) return res.json({ membership: updated });
  res.redirect(`/courses/${course_id}/members`);
}

function removeMember(req, res) {
  const course_id = Number(req.params.course_id);
  const membership_id = Number(req.params.membership_id);
  membershipRepo.removeMember(membership_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/members`);
}

function myMemberships(req, res) {
  console.log('me in session =', req.session.user); // ✅放前面
  const me = req.session.user;
  const memberships = membershipRepo.myMemberships(me.user_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ memberships });
  res.render('members/my', { memberships });
}
module.exports = { newMemberForm, addMember, listMembers, updateMemberRole, removeMember, myMemberships };
