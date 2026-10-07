const userRepository = require('../repositories/userRepository');
const membershipRepository = require('../repositories/membershipRepository');

function attachCurrentUser(req, res, next) {
  const userId = req.session.userId;
  if (userId) {
    req.currentUser = userRepository.findById(userId) || null;
  } else {
    req.currentUser = null;
  }
  next();
}

function requireLogin(req, res, next) {
  if (!req.currentUser) {
    if (req.path.startsWith('/api') || req.originalUrl.startsWith('/api')) {
      return res.status(401).json({ success: false, error: 'Authentication required' });
    }
    return res.redirect('/login');
  }
  next();
}

function requireAuthPage(req, res, next) {
  if (!req.currentUser) {
    return res.redirect('/login');
  }
  next();
}

function requireAuthApi(req, res, next) {
  if (!req.currentUser) {
    return res.status(401).json({ success: false, error: 'Authentication required' });
  }
  next();
}

function requireAdminPage(req, res, next) {
  if (!req.currentUser || req.currentUser.username !== 'admin') {
    return res.status(403).send('Forbidden');
  }
  next();
}

function requireAdminApi(req, res, next) {
  if (!req.currentUser || req.currentUser.username !== 'admin') {
    return res.status(403).json({ success: false, error: 'Admin access required' });
  }
  next();
}

function loadCourseMembership(req, res, next) {
  if (!req.currentUser) {
    if (req.path.startsWith('/api') || req.originalUrl.startsWith('/api')) {
      return res.status(401).json({ success: false, error: 'Authentication required' });
    }
    return res.redirect('/login');
  }

  const courseId = req.params.course_id;
  req.courseMembership = membershipRepository.findByCourseAndUser(courseId, req.currentUser.user_id) || null;
  next();
}

function requireCourseMember(req, res, next) {
  if (!req.courseMembership) {
    if (req.path.startsWith('/api') || req.originalUrl.startsWith('/api')) {
      return res.status(403).json({ success: false, error: 'Course membership required' });
    }
    return res.status(403).send('Course membership required');
  }
  next();
}

function requireTeacherOrAdmin(req, res, next) {
  const membership = req.courseMembership;
  const ok = membership && ['teacher', 'admin'].includes(membership.role_in_course);
  if (!ok) {
    if (req.path.startsWith('/api') || req.originalUrl.startsWith('/api')) {
      return res.status(403).json({ success: false, error: 'Teacher or admin role required' });
    }
    return res.status(403).send('Teacher or admin role required');
  }
  next();
}

function requireCourseStaff(req, res, next) {
  const membership = req.courseMembership;
  const ok = membership && ['teacher', 'admin', 'assistant', 'senior-assistant'].includes(membership.role_in_course);
  if (!ok) {
    if (req.path.startsWith('/api') || req.originalUrl.startsWith('/api')) {
      return res.status(403).json({ success: false, error: 'Course staff role required' });
    }
    return res.status(403).send('Course staff role required');
  }
  next();
}

module.exports = {
  attachCurrentUser,
  requireLogin,
  requireAuthPage,
  requireAuthApi,
  requireAdminPage,
  requireAdminApi,
  loadCourseMembership,
  requireCourseMember,
  requireTeacherOrAdmin,
  requireCourseStaff
};
