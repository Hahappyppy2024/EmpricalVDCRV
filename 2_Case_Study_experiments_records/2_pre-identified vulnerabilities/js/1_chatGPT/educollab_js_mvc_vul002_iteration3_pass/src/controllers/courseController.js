const courseRepo = require('../repos/courseRepo');
const membershipRepo = require('../repos/membershipRepo');

const STAFF_ROLES = new Set(['teacher', 'assistant', 'senior-assistant', 'admin']);

function isApi(req) {
  return req.path.startsWith('/api');
}

function deny(req, res) {
  if (isApi(req)) return res.status(403).json({ error: 'forbidden' });
  return res.status(403).render('403');
}

function getMembershipForUser(course_id, user_id) {
  if (!membershipRepo) return null;

  if (typeof membershipRepo.getMembership === 'function') {
    return membershipRepo.getMembership(course_id, user_id);
  }
  if (typeof membershipRepo.getByCourseAndUser === 'function') {
    return membershipRepo.getByCourseAndUser(course_id, user_id);
  }
  if (typeof membershipRepo.getByUserCourse === 'function') {
    return membershipRepo.getByUserCourse(user_id, course_id);
  }
  if (typeof membershipRepo.findByCourseUser === 'function') {
    return membershipRepo.findByCourseUser(course_id, user_id);
  }
  if (typeof membershipRepo.listMembers === 'function') {
    const members = membershipRepo.listMembers(course_id) || [];
    return members.find((m) => Number(m.user_id) === Number(user_id)) || null;
  }

  return null;
}

function canManageCourse(course, me) {
  if (!course || !me) return false;
  if (me.username === 'admin') return true;
  if (Number(course.created_by) === Number(me.user_id)) return true;
  const membership = getMembershipForUser(course.course_id, me.user_id);
  const role = membership && membership.role_in_course;
  return STAFF_ROLES.has(role);
}

function newCourseForm(req, res) {
  res.render('courses/new');
}

function createCourse(req, res) {
  const { title, description } = req.body;
  if (!title || !description) {
    if (req.path.startsWith('/api')) return res.status(400).json({ error: 'title and description required' });
    return res.status(400).render('courses/new', { error: 'title and description required' });
  }
  const me = req.session.user;
  const course = courseRepo.createCourse({ title, description, created_by: me.user_id });

  // auto-add creator to course membership
  const role = me.username === 'admin' ? 'admin' : 'teacher';
  try {
    membershipRepo.addMember({ course_id: course.course_id, user_id: me.user_id, role_in_course: role });
  } catch (e) {
    // ignore unique constraint
  }

  if (req.path.startsWith('/api')) return res.status(201).json({ course });
  res.redirect(`/courses/${course.course_id}`);
}

function listCourses(req, res) {
  const courses = courseRepo.listCourses();
  if (req.path.startsWith('/api')) return res.json({ courses });
  res.render('courses/list', { courses });
}

function getCourse(req, res) {
  const course_id = Number(req.params.course_id);
  const course = courseRepo.getById(course_id);
  if (!course) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'course not found' });
    return res.status(404).render('404');
  }
  if (req.path.startsWith('/api')) return res.json({ course });
  res.render('courses/detail', { course });
}

function editCourseForm(req, res) {
  const course_id = Number(req.params.course_id);
  const course = courseRepo.getById(course_id);
  if (!course) {
    if (isApi(req)) return res.status(404).json({ error: 'course not found' });
    return res.status(404).render('404');
  }
  if (!canManageCourse(course, req.session.user)) return deny(req, res);
  res.render('courses/edit', { course });
}

function updateCourse(req, res) {
  const course_id = Number(req.params.course_id);
  const { title, description } = req.body;
  const course = courseRepo.getById(course_id);
  if (!course) {
    if (isApi(req)) return res.status(404).json({ error: 'course not found' });
    return res.status(404).render('404');
  }
  if (!canManageCourse(course, req.session.user)) return deny(req, res);
  const updated = courseRepo.updateCourse(course_id, { title: title || course.title, description: description || course.description });
  if (isApi(req)) return res.json({ course: updated });
  res.redirect(`/courses/${course_id}`);
}

function deleteCourse(req, res) {
  const course_id = Number(req.params.course_id);
  const course = courseRepo.getById(course_id);
  if (!course) {
    if (isApi(req)) return res.status(404).json({ error: 'course not found' });
    return res.status(404).render('404');
  }
  if (!canManageCourse(course, req.session.user)) return deny(req, res);
  courseRepo.deleteCourse(course_id);
  if (isApi(req)) return res.json({ ok: true });
  res.redirect('/courses');
}

module.exports = {
  newCourseForm,
  createCourse,
  listCourses,
  getCourse,
  editCourseForm,
  updateCourse,
  deleteCourse
};