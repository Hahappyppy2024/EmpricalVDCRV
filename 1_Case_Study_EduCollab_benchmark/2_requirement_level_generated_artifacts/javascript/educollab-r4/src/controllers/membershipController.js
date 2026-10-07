const userRepository = require('../repositories/userRepository');
const courseRepository = require('../repositories/courseRepository');
const membershipRepository = require('../repositories/membershipRepository');

const validRoles = ['admin', 'teacher', 'student', 'assistant', 'senior-assistant'];

function showAddMember(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  res.render('course_member_new', {
    course,
    users: userRepository.listUsers(),
    roles: validRoles,
    error: null
  });
}

function addMember(req, res) {
  const courseId = Number(req.params.course_id);
  const { user_id, role_in_course } = req.body;
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  if (!user_id || !validRoles.includes(role_in_course)) {
    return res.status(400).render('course_member_new', {
      course,
      users: userRepository.listUsers(),
      roles: validRoles,
      error: 'user_id and valid role_in_course are required'
    });
  }
  try {
    membershipRepository.addMember({ course_id: courseId, user_id: Number(user_id), role_in_course });
  } catch (err) {
    return res.status(400).render('course_member_new', {
      course,
      users: userRepository.listUsers(),
      roles: validRoles,
      error: 'Unable to add member'
    });
  }
  return res.redirect(`/courses/${courseId}/members`);
}

function apiAddMember(req, res) {
  const courseId = Number(req.params.course_id);
  const { user_id, role_in_course } = req.body;
  if (!user_id || !validRoles.includes(role_in_course)) {
    return res.status(400).json({ success: false, error: 'user_id and valid role_in_course are required' });
  }
  try {
    const membership = membershipRepository.addMember({ course_id: courseId, user_id: Number(user_id), role_in_course });
    return res.status(201).json({ success: true, membership });
  } catch (err) {
    return res.status(400).json({ success: false, error: 'Unable to add member' });
  }
}

function listMembers(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('course_members_list', {
    course,
    members: membershipRepository.listMembersByCourse(course.course_id),
    roles: validRoles
  });
}

function apiListMembers(req, res) {
  res.json({
    success: true,
    members: membershipRepository.listMembersByCourse(Number(req.params.course_id))
  });
}

function updateMemberRole(req, res) {
  const courseId = Number(req.params.course_id);
  const membershipId = Number(req.params.membership_id);
  const { role_in_course } = req.body;
  if (!validRoles.includes(role_in_course)) {
    return res.status(400).send('Invalid role');
  }
  const existing = membershipRepository.findById(membershipId);
  if (!existing || existing.course_id !== courseId) return res.status(404).send('Membership not found');
  membershipRepository.updateMemberRole(membershipId, role_in_course);
  return res.redirect(`/courses/${courseId}/members`);
}

function apiUpdateMemberRole(req, res) {
  const courseId = Number(req.params.course_id);
  const membershipId = Number(req.params.membership_id);
  const { role_in_course } = req.body;
  if (!validRoles.includes(role_in_course)) {
    return res.status(400).json({ success: false, error: 'Invalid role' });
  }
  const existing = membershipRepository.findById(membershipId);
  if (!existing || existing.course_id !== courseId) {
    return res.status(404).json({ success: false, error: 'Membership not found' });
  }
  const membership = membershipRepository.updateMemberRole(membershipId, role_in_course);
  return res.json({ success: true, membership });
}

function removeMember(req, res) {
  const courseId = Number(req.params.course_id);
  const membershipId = Number(req.params.membership_id);
  const existing = membershipRepository.findById(membershipId);
  if (!existing || existing.course_id !== courseId) return res.status(404).send('Membership not found');
  membershipRepository.removeMember(membershipId);
  return res.redirect(`/courses/${courseId}/members`);
}

function apiRemoveMember(req, res) {
  const courseId = Number(req.params.course_id);
  const membershipId = Number(req.params.membership_id);
  const existing = membershipRepository.findById(membershipId);
  if (!existing || existing.course_id !== courseId) {
    return res.status(404).json({ success: false, error: 'Membership not found' });
  }
  membershipRepository.removeMember(membershipId);
  return res.json({ success: true });
}

function myMemberships(req, res) {
  res.render('my_memberships', {
    memberships: membershipRepository.listMembershipsByUser(req.currentUser.user_id)
  });
}

function apiMyMemberships(req, res) {
  res.json({
    success: true,
    memberships: membershipRepository.listMembershipsByUser(req.currentUser.user_id)
  });
}

module.exports = {
  showAddMember, addMember, apiAddMember,
  listMembers, apiListMembers,
  updateMemberRole, apiUpdateMemberRole,
  removeMember, apiRemoveMember,
  myMemberships, apiMyMemberships
};
