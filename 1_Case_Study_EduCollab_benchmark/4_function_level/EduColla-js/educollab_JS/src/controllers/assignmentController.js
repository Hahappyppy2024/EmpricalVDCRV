const assignmentRepo = require('../repos/assignmentRepo');

function newAssignmentForm(req, res) {
  res.render('assignments/new', { course_id: Number(req.params.course_id) });
}

function createAssignment(req, res) {
  const course_id = Number(req.params.course_id);
  const { title, description, due_at } = req.body;
  const me = req.session.user;
  const a = assignmentRepo.createAssignment({ course_id, created_by: me.user_id, title, description, due_at });
  if (req.originalUrl.startsWith('/api')) return res.status(201).json({ assignment: a });
  res.redirect(`/courses/${course_id}/assignments/${a.assignment_id}`);
}

function listAssignments(req, res) {
  const course_id = Number(req.params.course_id);
  const assignments = assignmentRepo.listAssignments(course_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ assignments });
  res.render('assignments/list', { course_id, assignments });
}

function getAssignment(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const assignment = assignmentRepo.getById(course_id, assignment_id);
  if (!assignment) {
    if (req.originalUrl.startsWith('/api')) return res.status(404).json({ error: 'assignment not found' });
    return res.status(404).render('404');
  }
  if (req.originalUrl.startsWith('/api')) return res.json({ assignment });
  res.render('assignments/detail', { course_id, assignment });
}

function editAssignmentForm(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const assignment = assignmentRepo.getById(course_id, assignment_id);
  if (!assignment) return res.status(404).render('404');
  res.render('assignments/edit', { course_id, assignment });
}

function updateAssignment(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const { title, description, due_at } = req.body;
  const assignment = assignmentRepo.updateAssignment(course_id, assignment_id, { title, description, due_at });
  if (req.originalUrl.startsWith('/api')) return res.json({ assignment });
  res.redirect(`/courses/${course_id}/assignments/${assignment_id}`);
}

function deleteAssignment(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  assignmentRepo.deleteAssignment(course_id, assignment_id);
  if (req.originalUrl.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/assignments`);
}

module.exports = {
  newAssignmentForm,
  createAssignment,
  listAssignments,
  getAssignment,
  editAssignmentForm,
  updateAssignment,
  deleteAssignment
};
