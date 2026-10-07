const courseRepo = require('../repos/courseRepo');
const membershipRepo = require('../repos/membershipRepo');

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
  if (!course) return res.status(404).render('404');
  res.render('courses/edit', { course });
}

function updateCourse(req, res) {
  const course_id = Number(req.params.course_id);
  const { title, description } = req.body;
  const course = courseRepo.getById(course_id);
  if (!course) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'course not found' });
    return res.status(404).render('404');
  }
  const updated = courseRepo.updateCourse(course_id, { title: title || course.title, description: description || course.description });
  if (req.path.startsWith('/api')) return res.json({ course: updated });
  res.redirect(`/courses/${course_id}`);
}

function deleteCourse(req, res) {
  const course_id = Number(req.params.course_id);
  const course = courseRepo.getById(course_id);
  if (!course) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'course not found' });
    return res.status(404).render('404');
  }
  courseRepo.deleteCourse(course_id);
  if (req.path.startsWith('/api')) return res.json({ ok: true });
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
