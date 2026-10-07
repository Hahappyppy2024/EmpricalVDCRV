const assignmentRepository = require('../repositories/assignmentRepository');
const courseRepository = require('../repositories/courseRepository');
const submissionRepository = require('../repositories/submissionRepository');
const membershipRepository = require('../repositories/membershipRepository');

function showSubmit(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const course = courseRepository.findById(courseId);
  const assignment = assignmentRepository.findById(courseId, assignmentId);
  if (!course || !assignment) return res.status(404).send('Assignment not found');
  res.render('submission_new', { course, assignment, error: null });
}

function createSubmission(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const { content_text, attachment_upload_id } = req.body;
  if (!assignmentRepository.findById(courseId, assignmentId)) return res.status(404).send('Assignment not found');
  const submission = submissionRepository.createSubmission({
    assignment_id: assignmentId,
    course_id: courseId,
    student_id: req.currentUser.user_id,
    content_text,
    attachment_upload_id: attachment_upload_id || null
  });
  return res.redirect(`/my/submissions`);
}

function apiCreateSubmission(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const { content_text, attachment_upload_id } = req.body;
  if (!assignmentRepository.findById(courseId, assignmentId)) {
    return res.status(404).json({ success: false, error: 'Assignment not found' });
  }
  const submission = submissionRepository.createSubmission({
    assignment_id: assignmentId,
    course_id: courseId,
    student_id: req.currentUser.user_id,
    content_text,
    attachment_upload_id: attachment_upload_id || null
  });
  return res.status(201).json({ success: true, submission });
}

function updateMySubmission(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const submissionId = Number(req.params.submission_id);
  const { content_text, attachment_upload_id } = req.body;
  const submission = submissionRepository.findById(courseId, assignmentId, submissionId);
  if (!submission) return res.status(404).send('Submission not found');
  if (submission.student_id !== req.currentUser.user_id) return res.status(403).send('Own submission required');
  submissionRepository.updateSubmission(courseId, assignmentId, submissionId, { content_text, attachment_upload_id });
  return res.redirect('/my/submissions');
}

function apiUpdateMySubmission(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const submissionId = Number(req.params.submission_id);
  const { content_text, attachment_upload_id } = req.body;
  const submission = submissionRepository.findById(courseId, assignmentId, submissionId);
  if (!submission) return res.status(404).json({ success: false, error: 'Submission not found' });
  if (submission.student_id !== req.currentUser.user_id) {
    return res.status(403).json({ success: false, error: 'Own submission required' });
  }
  const updated = submissionRepository.updateSubmission(courseId, assignmentId, submissionId, {
    content_text, attachment_upload_id
  });
  return res.json({ success: true, submission: updated });
}

function listMySubmissions(req, res) {
  res.render('my_submissions', {
    submissions: submissionRepository.listByStudent(req.currentUser.user_id)
  });
}

function apiListMySubmissions(req, res) {
  res.json({
    success: true,
    submissions: submissionRepository.listByStudent(req.currentUser.user_id)
  });
}

function listSubmissionsForAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const course = courseRepository.findById(courseId);
  const assignment = assignmentRepository.findById(courseId, assignmentId);
  if (!course || !assignment) return res.status(404).send('Assignment not found');
  const membership = membershipRepository.findByCourseAndUser(courseId, req.currentUser.user_id);
  const role = membership && membership.role_in_course;
  if (!role || !['teacher', 'admin', 'assistant', 'senior-assistant'].includes(role)) {
    return res.status(403).send('Course staff role required');
  }
  res.render('assignment_submissions_list', {
    course,
    assignment,
    submissions: submissionRepository.listByAssignment(courseId, assignmentId)
  });
}

function apiListSubmissionsForAssignment(req, res) {
  const courseId = Number(req.params.course_id);
  const assignmentId = Number(req.params.assignment_id);
  const membership = membershipRepository.findByCourseAndUser(courseId, req.currentUser.user_id);
  const role = membership && membership.role_in_course;
  if (!role || !['teacher', 'admin', 'assistant', 'senior-assistant'].includes(role)) {
    return res.status(403).json({ success: false, error: 'Course staff role required' });
  }
  res.json({
    success: true,
    submissions: submissionRepository.listByAssignment(courseId, assignmentId)
  });
}

module.exports = {
  showSubmit, createSubmission, apiCreateSubmission,
  updateMySubmission, apiUpdateMySubmission,
  listMySubmissions, apiListMySubmissions,
  listSubmissionsForAssignment, apiListSubmissionsForAssignment
};
