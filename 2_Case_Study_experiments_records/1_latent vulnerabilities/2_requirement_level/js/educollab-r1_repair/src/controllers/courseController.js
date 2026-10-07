const courseRepository = require('../repositories/courseRepository');

function showNewCourse(req, res) {
  res.render('course_new', { error: null });
}

function createCourse(req, res) {
  const { title, description } = req.body;

  if (!title) {
    return res.status(400).render('course_new', { error: 'Title is required.' });
  }

  const course = courseRepository.createCourse({
    title,
    description: description || '',
    created_by: req.currentUser.user_id
  });

  return res.redirect(`/courses/${course.course_id}`);
}

function apiCreateCourse(req, res) {
  const { title, description } = req.body;

  if (!title) {
    return res.status(400).json({ success: false, error: 'title is required' });
  }

  const course = courseRepository.createCourse({
    title,
    description: description || '',
    created_by: req.currentUser.user_id
  });

  return res.status(201).json({ success: true, course });
}

function listCourses(req, res) {
  const courses = courseRepository.listCourses();
  res.render('courses_list', { courses });
}

function apiListCourses(req, res) {
  const courses = courseRepository.listCourses();
  res.json({ success: true, courses });
}

function getCourse(req, res) {
  const course = courseRepository.findById(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }
  res.render('course_show', { course });
}

function apiGetCourse(req, res) {
  const course = courseRepository.findById(req.params.course_id);
  if (!course) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }
  res.json({ success: true, course });
}

function canManageCourse(user, course) {
  return Boolean(user && course && (user.is_admin || user.user_id === course.created_by));
}

function denyCourseAccess(req, res) {
  if (req.path.startsWith('/api/')) {
    return res.status(403).json({ success: false, error: 'Forbidden' });
  }
  return res.status(403).send('Forbidden');
}

function showEditCourse(req, res) {
  const course = courseRepository.findById(req.params.course_id);
  if (!course) {
    return res.status(404).send('Course not found');
  }
  if (!canManageCourse(req.currentUser, course)) {
    return res.status(403).send('Forbidden');
  }
  res.render('course_edit', { course, error: null });
}

function updateCourse(req, res) {
  const { title, description } = req.body;
  const existing = courseRepository.findById(req.params.course_id);

  if (!existing) {
    return res.status(404).send('Course not found');
  }
  if (!canManageCourse(req.currentUser, existing)) {
    return denyCourseAccess(req, res);
  }

  if (!title) {
    return res.status(400).render('course_edit', {
      course: existing,
      error: 'Title is required.'
    });
  }

  courseRepository.updateCourse(req.params.course_id, {
    title,
    description: description || ''
  });

  return res.redirect(`/courses/${req.params.course_id}`);
}

function apiUpdateCourse(req, res) {
  const { title, description } = req.body;
  const existing = courseRepository.findById(req.params.course_id);

  if (!existing) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }
  if (!canManageCourse(req.currentUser, existing)) {
    return denyCourseAccess(req, res);
  }

  if (!title) {
    return res.status(400).json({ success: false, error: 'title is required' });
  }

  const course = courseRepository.updateCourse(req.params.course_id, {
    title,
    description: description || ''
  });

  return res.json({ success: true, course });
}

function deleteCourse(req, res) {
  const existing = courseRepository.findById(req.params.course_id);
  if (!existing) {
    return res.status(404).send('Course not found');
  }
  if (!canManageCourse(req.currentUser, existing)) {
    return denyCourseAccess(req, res);
  }

  courseRepository.deleteCourse(req.params.course_id);
  return res.redirect('/courses');
}

function apiDeleteCourse(req, res) {
  const existing = courseRepository.findById(req.params.course_id);
  if (!existing) {
    return res.status(404).json({ success: false, error: 'Course not found' });
  }
  if (!canManageCourse(req.currentUser, existing)) {
    return denyCourseAccess(req, res);
  }

  courseRepository.deleteCourse(req.params.course_id);
  return res.json({ success: true });
}

module.exports = {
  showNewCourse,
  createCourse,
  apiCreateCourse,
  listCourses,
  apiListCourses,
  getCourse,
  apiGetCourse,
  showEditCourse,
  updateCourse,
  apiUpdateCourse,
  deleteCourse,
  apiDeleteCourse
};