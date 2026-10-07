const courseRepository = require('../repositories/courseRepository');
const userRepository = require('../repositories/userRepository');
const membershipRepository = require('../repositories/membershipRepository');

function ensureCourse(courseId) {
  return courseRepository.findById(courseId);
}

function showAddMember(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const users = userRepository.listUsers();
  return res.render('course_member_new', {
    course,
    users,
    roles: membershipRepository.ALLOWED_ROLES,
    error: null
  });
}

function addMember(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const { user_id, role_in_course } = req.body;
  const users = userRepository.listUsers();

  if (!user_id || !membershipRepository.isValidRole(role_in_course)) {
    return res.status(400).render('course_member_new', {
      course,
      users,
      roles: membershipRepository.ALLOWED_ROLES,
      error: 'Valid user_id and role_in_course are required.'
    });
  }

  const user = userRepository.findById(user_id);
  if (!user) {
    return res.status(400).render('course_member_new', {
      course,
      users,
      roles: membershipRepository.ALLOWED_ROLES,
      error: 'User not found.'
    });
  }

  const existing = membershipRepository.findByCourseAndUser(req.params.course_id, user_id);
  if (existing) {
    return res.status(400).render('course_member_new', {
      course,
      users,
      roles: membershipRepository.ALLOWED_ROLES,
      error: 'User is already a member of this course.'
    });
  }

  membershipRepository.addMembership({
    course_id: req.params.course_id,
    user_id,
    role_in_course
  });

  return res.redirect(`/courses/${req.params.course_id}/members`);
}

function apiAddMember(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const { user_id, role_in_course } = req.body;
  if (!user_id || !membershipRepository.isValidRole(role_in_course)) {
    return res.status(400).json({ success: false, error: 'Valid user_id and role_in_course are required' });
  }

  const user = userRepository.findById(user_id);
  if (!user) {
    return res.status(400).json({ success: false, error: 'User not found' });
  }

  const existing = membershipRepository.findByCourseAndUser(req.params.course_id, user_id);
  if (existing) {
    return res.status(400).json({ success: false, error: 'User is already a member of this course' });
  }

  const membership = membershipRepository.addMembership({
    course_id: req.params.course_id,
    user_id,
    role_in_course
  });

  return res.status(201).json({ success: true, membership });
}

function listMembers(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const memberships = membershipRepository.listByCourse(req.params.course_id);
  return res.render('course_members_list', { course, memberships, roles: membershipRepository.ALLOWED_ROLES });
}

function apiListMembers(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const memberships = membershipRepository.listByCourse(req.params.course_id);
  return res.json({ success: true, memberships });
}

function updateMemberRole(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const membership = membershipRepository.findById(req.params.membership_id);
  if (!membership || String(membership.course_id) !== String(req.params.course_id)) {
    return res.status(404).send('Membership not found');
  }

  const { role_in_course } = req.body;
  if (!membershipRepository.isValidRole(role_in_course)) {
    const memberships = membershipRepository.listByCourse(req.params.course_id);
    return res.status(400).render('course_members_list', {
      course,
      memberships,
      roles: membershipRepository.ALLOWED_ROLES
    });
  }

  membershipRepository.updateMembershipRole(req.params.membership_id, role_in_course);
  return res.redirect(`/courses/${req.params.course_id}/members`);
}

function apiUpdateMemberRole(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const membership = membershipRepository.findById(req.params.membership_id);
  if (!membership || String(membership.course_id) !== String(req.params.course_id)) {
    return res.status(404).json({ success: false, error: 'Membership not found' });
  }

  const { role_in_course } = req.body;
  if (!membershipRepository.isValidRole(role_in_course)) {
    return res.status(400).json({ success: false, error: 'Valid role_in_course is required' });
  }

  const updated = membershipRepository.updateMembershipRole(req.params.membership_id, role_in_course);
  return res.json({ success: true, membership: updated });
}

function removeMember(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }

  const membership = membershipRepository.findById(req.params.membership_id);
  if (!membership || String(membership.course_id) !== String(req.params.course_id)) {
    return res.status(404).send('Membership not found');
  }

  membershipRepository.removeMembership(req.params.membership_id);
  return res.redirect(`/courses/${req.params.course_id}/members`);
}

function apiRemoveMember(req, res) {
  const course = ensureCourse(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }

  const membership = membershipRepository.findById(req.params.membership_id);
  if (!membership || String(membership.course_id) !== String(req.params.course_id)) {
    return res.status(404).json({ success: false, error: 'Membership not found' });
  }

  membershipRepository.removeMembership(req.params.membership_id);
  return res.json({ success: true });
}

function myMemberships(req, res) {
  const memberships = membershipRepository.listByUser(req.currentUser.user_id);
  return res.render('my_memberships', { memberships });
}

function apiMyMemberships(req, res) {
  const memberships = membershipRepository.listByUser(req.currentUser.user_id);
  return res.json({ success: true, memberships });
}

module.exports = {
  showAddMember,
  addMember,
  apiAddMember,
  listMembers,
  apiListMembers,
  updateMemberRole,
  apiUpdateMemberRole,
  removeMember,
  apiRemoveMember,
  myMemberships,
  apiMyMemberships
};
