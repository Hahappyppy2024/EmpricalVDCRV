const membershipRepository = require('../repositories/membershipRepository');
const userRepository = require('../repositories/userRepository');

function attachCurrentUser(req, res, next) {
  const userId = req.session.userId;
  req.currentUser = userId ? (userRepository.findById(userId) || null) : null;
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
  return requireLogin(req, res, next);
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

function loadMembership(req, res, next) {
  const courseId = Number(req.params.course_id);
  if (!req.currentUser) {
    if (req.originalUrl.startsWith('/api')) {
      return res.status(401).json({ success: false, error: 'Authentication required' });
    }
    return res.redirect('/login');
  }
  const membership = membershipRepository.findByCourseAndUser(courseId, req.currentUser.user_id);
  req.courseMembership = membership || null;
  next();
}

function requireCourseMember(req, res, next) {
  loadMembership(req, res, () => {
    if (!req.courseMembership) {
      if (req.originalUrl.startsWith('/api')) {
        return res.status(403).json({ success: false, error: 'Course membership required' });
      }
      return res.status(403).send('Course membership required');
    }
    next();
  });
}

function requireTeacherOrAdmin(req, res, next) {
  loadMembership(req, res, () => {
    const role = req.courseMembership && req.courseMembership.role_in_course;
    if (!role || !['teacher', 'admin'].includes(role)) {
      if (req.originalUrl.startsWith('/api')) {
        return res.status(403).json({ success: false, error: 'Teacher or admin role required' });
      }
      return res.status(403).send('Teacher or admin role required');
    }
    next();
  });
}

function requireCourseStaff(req, res, next) {
  loadMembership(req, res, () => {
    const role = req.courseMembership && req.courseMembership.role_in_course;
    if (!role || !['teacher', 'admin', 'assistant', 'senior-assistant'].includes(role)) {
      if (req.originalUrl.startsWith('/api')) {
        return res.status(403).json({ success: false, error: 'Course staff role required' });
      }
      return res.status(403).send('Course staff role required');
    }
    next();
  });
}

module.exports = {
  attachCurrentUser,
  requireLogin,
  requireAuthPage,
  requireAuthApi,
  requireAdminPage,
  requireAdminApi,
  requireCourseMember,
  requireTeacherOrAdmin,
  requireCourseStaff
};
