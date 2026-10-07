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
    if (req.path.startsWith('/api')) return res.status(201).json({ membership: member });
    return res.redirect(`/courses/${course_id}/members`);
  } catch (e) {
    if (req.path.startsWith('/api')) return res.status(400).json({ error: 'failed to add member (maybe already added)' });
    return res.status(400).render('members/new', {
      course_id,
      users: userRepo.listUsers(),
      roles: ['admin', 'teacher', 'student', 'assistant', 'senior-assistant'],
      error: 'Failed to add (maybe already a member)'
    });
  }
}

function listMembers(req, res) {
  const course_id = Number(req.params.course_id);
  const members = membershipRepo.listMembers(course_id);
  if (req.path.startsWith('/api')) return res.json({ members });
  res.render('members/list', { course_id, members, roles: ['admin', 'teacher', 'student', 'assistant', 'senior-assistant'] });
}

function updateMemberRole(req, res) {
  const course_id = Number(req.params.course_id);
  const membership_id = Number(req.params.membership_id);
  const { role_in_course } = req.body;

  const target = membershipRepo.getById(membership_id);
  if (!target || Number(target.course_id) !== course_id) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'membership not found' });
    return res.status(404).render('404');
  }

  const updated = membershipRepo.updateRoleInCourse(course_id, membership_id, role_in_course);
  if (!updated) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'membership not found' });
    return res.status(404).render('404');
  }

  if (req.path.startsWith('/api')) return res.json({ membership: updated });
  res.redirect(`/courses/${course_id}/members`);
}

function removeMember(req, res) {
  const course_id = Number(req.params.course_id);
  const membership_id = Number(req.params.membership_id);

  const target = membershipRepo.getById(membership_id);
  if (!target || Number(target.course_id) !== course_id) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'membership not found' });
    return res.status(404).render('404');
  }

  membershipRepo.removeMemberInCourse(course_id, membership_id);

  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/members`);
}

function myMemberships(req, res) {
  const me = req.session.user;
  const memberships = membershipRepo.myMemberships(me.user_id);
  if (req.path.startsWith('/api')) return res.json({ memberships });
  res.render('members/my', { memberships });
}

module.exports = { newMemberForm, addMember, listMembers, updateMemberRole, removeMember, myMemberships };