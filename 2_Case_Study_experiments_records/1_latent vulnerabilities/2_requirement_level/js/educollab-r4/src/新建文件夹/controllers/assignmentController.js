const courseRepository = require('../repositories/courseRepository');
const assignmentRepository = require('../repositories/assignmentRepository');

function showNewAssignment(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('assignment_new', { course, error: null });
}

function createAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  const { title, description, due_at } = req.body;
  if (!course) return res.status(404).send('Course not found');
  if (!title) return res.status(400).render('assignment_new', { course, error: 'Title is required.' });
  const assignment = assignmentRepository.createAssignment({
    course_id: courseId,
    created_by: req.currentUser.user_id,
    title,
    description,
    due_at
  });
  return res.redirect(`/courses/${courseId}/assignments/${assignment.assignment_id}`);
}

function apiCreateAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const { title, description, due_at } = req.body;
  if (!title) return res.status(400).json({ success: false, error: 'title is required' });
  const assignment = assignmentRepository.createAssignment({
    course_id: courseId,
    created_by: req.currentUser.user_id,
    title,
    description,
    due_at
  });
  return res.status(201).json({ success: true, assignment });
}

function listAssignments(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('assignments_list', { course, assignments: assignmentRepository.listByCourse(course.course_id) });
}

function apiListAssignments(req, res) {
  res.json({ success: true, assignments: assignmentRepository.listByCourse(Number(req.params.course_id)) });
}

function getAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const course = courseRepository.findById(courseId);
  const assignment = assignmentRepository.findById(courseId, assignmentId);
  if (!course || !assignment) return res.status(404).send('Assignment not found');
  res.render('assignment_show', { course, assignment });
}

function apiGetAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const assignment = assignmentRepository.findById(courseId, assignmentId);
  if (!assignment) return res.status(404).json({ success: false, error: 'Assignment not found' });
  res.json({ success: true, assignment });
}

function showEditAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const course = courseRepository.findById(courseId);
  const assignment = assignmentRepository.findById(courseId, assignmentId);
  if (!course || !assignment) return res.status(404).send('Assignment not found');
  res.render('assignment_edit', { course, assignment, error: null });
}

function updateAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const { title, description, due_at } = req.body;
  const existing = assignmentRepository.findById(courseId, assignmentId);
  if (!existing) return res.status(404).send('Assignment not found');
  if (!title) {
    return res.status(400).render('assignment_edit', {
      course: courseRepository.findById(courseId),
      assignment: existing,
      error: 'Title is required.'
    });
  }
  assignmentRepository.updateAssignment(courseId, assignmentId, { title, description, due_at });
  return res.redirect(`/courses/${courseId}/assignments/${assignmentId}`);
}

function apiUpdateAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const { title, description, due_at } = req.body;
  if (!assignmentRepository.findById(courseId, assignmentId)) {
    return res.status(404).json({ success: false, error: 'Assignment not found' });
  }
  if (!title) return res.status(400).json({ success: false, error: 'title is required' });
  const assignment = assignmentRepository.updateAssignment(courseId, assignmentId, { title, description, due_at });
  return res.json({ success: true, assignment });
}

function deleteAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  if (!assignmentRepository.findById(courseId, assignmentId)) return res.status(404).send('Assignment not found');
  assignmentRepository.deleteAssignment(courseId, assignmentId);
  return res.redirect(`/courses/${courseId}/assignments`);
}

function apiDeleteAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  if (!assignmentRepository.findById(courseId, assignmentId)) {
    return res.status(404).json({ success: false, error: 'Assignment not found' });
  }
  assignmentRepository.deleteAssignment(courseId, assignmentId);
  return res.json({ success: true });
}

module.exports = {
  showNewAssignment, createAssignment, apiCreateAssignment,
  listAssignments, apiListAssignments,
  getAssignment, apiGetAssignment,
  showEditAssignment, updateAssignment, apiUpdateAssignment,
  deleteAssignment, apiDeleteAssignment
};
