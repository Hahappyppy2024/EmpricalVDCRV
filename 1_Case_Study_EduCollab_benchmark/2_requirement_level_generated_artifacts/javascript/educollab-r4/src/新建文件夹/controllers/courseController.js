const courseRepository = require('../repositories/courseRepository');
const membershipRepository = require('../repositories/membershipRepository');

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
    description,
    created_by: req.currentUser.user_id
  });
  membershipRepository.addMember({
    course_id: course.course_id,
    user_id: req.currentUser.user_id,
    role_in_course: 'teacher'
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
    description,
    created_by: req.currentUser.user_id
  });
  membershipRepository.addMember({
    course_id: course.course_id,
    user_id: req.currentUser.user_id,
    role_in_course: 'teacher'
  });
  return res.status(201).json({ success: true, course });
}

function listCourses(req, res) {
  res.render('courses_list', { courses: courseRepository.listCourses() });
}

function apiListCourses(req, res) {
  res.json({ success: true, courses: courseRepository.listCourses() });
}

function getCourse(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('course_show', { course });
}

function apiGetCourse(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).json({ success: false, error: 'Course not found' });
  res.json({ success: true, course });
}

function showEditCourse(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('course_edit', { course, error: null });
}

function updateCourse(req, res) {
  const courseId = Number(req.params.course_id);
  const existing = courseRepository.findById(courseId);
  const { title, description } = req.body;
  if (!existing) return res.status(404).send('Course not found');
  if (!title) return res.status(400).render('course_edit', { course: existing, error: 'Title is required.' });
  courseRepository.updateCourse(courseId, { title, description });
  return res.redirect(`/courses/${courseId}`);
}

function apiUpdateCourse(req, res) {
  const courseId = Number(req.params.course_id);
  const existing = courseRepository.findById(courseId);
  const { title, description } = req.body;
  if (!existing) return res.status(404).json({ success: false, error: 'Course not found' });
  if (!title) return res.status(400).json({ success: false, error: 'title is required' });
  const course = courseRepository.updateCourse(courseId, { title, description });
  return res.json({ success: true, course });
}

function deleteCourse(req, res) {
  const courseId = Number(req.params.course_id);
  if (!courseRepository.findById(courseId)) return res.status(404).send('Course not found');
  courseRepository.deleteCourse(courseId);
  return res.redirect('/courses');
}

function apiDeleteCourse(req, res) {
  const courseId = Number(req.params.course_id);
  if (!courseRepository.findById(courseId)) return res.status(404).json({ success: false, error: 'Course not found' });
  courseRepository.deleteCourse(courseId);
  return res.json({ success: true });
}

module.exports = {
  showNewCourse, createCourse, apiCreateCourse,
  listCourses, apiListCourses,
  getCourse, apiGetCourse,
  showEditCourse, updateCourse, apiUpdateCourse,
  deleteCourse, apiDeleteCourse
};
