const membershipRepo = require('../repos/membershipRepo');

const isApi = (req) => req.originalUrl.startsWith('/api');

function requireLogin(req, res, next) {
  if (!req.session.user) {
    if (isApi(req)) return res.status(401).json({ error: 'Unauthorized' });
    return res.redirect('/login');
  }
  next();
}

function requireAdmin(req, res, next) {
  if (!req.session.user || req.session.user.username !== 'admin') {
    if (isApi(req)) return res.status(403).json({ error: 'Forbidden' });
    return res.status(403).render('403');
  }
  next();
}

function requireCourseMember(req, res, next) {
  const courseId = Number(req.params.course_id);
  const me = req.session.user;
  if (me.username === 'admin') return next();

  const m = membershipRepo.getByCourseAndUser(courseId, me.user_id);
  if (!m) {
    if (isApi(req)) return res.status(403).json({ error: 'Not a course member' });
    return res.status(403).render('403');
  }
  req.courseMembership = m;
  next();
}

function requireCourseStaff(req, res, next) {
  const me = req.session.user;
  if (me.username === 'admin') return next();
  const role = (req.courseMembership && req.courseMembership.role_in_course) || '';
  const allowed = ['teacher', 'assistant', 'senior-assistant', 'admin'];
  if (!allowed.includes(role)) {
    if (isApi(req)) return res.status(403).json({ error: 'Staff role required' });
    return res.status(403).render('403');
  }
  next();
}

function requireTeacherOrAdmin(req, res, next) {
  const me = req.session.user;
  if (me.username === 'admin') return next();
  const role = (req.courseMembership && req.courseMembership.role_in_course) || '';
  const allowed = ['teacher', 'admin'];
  if (!allowed.includes(role)) {
    if (isApi(req)) return res.status(403).json({ error: 'Teacher/admin role required' });
    return res.status(403).render('403');
  }
  next();
}

function requireStudent(req, res, next) {
  const me = req.session.user;
  if (me.username === 'admin') return next();
  const role = (req.courseMembership && req.courseMembership.role_in_course) || '';
  if (role !== 'student') {
    if (isApi(req)) return res.status(403).json({ error: 'Student role required' });
    return res.status(403).render('403');
  }
  next();
}

module.exports = {
  requireLogin,
  requireAdmin,
  requireCourseMember,
  requireCourseStaff,
  requireTeacherOrAdmin,
  requireStudent,
};