const assignmentRepository = require('../repositories/assignmentRepository');
const courseRepository = require('../repositories/courseRepository');
const submissionRepository = require('../repositories/submissionRepository');
const membershipRepository = require('../repositories/membershipRepository');
const uploadRepository = require('../repositories/uploadRepository');

function validateAttachmentUploadId(courseId, rawId) {
  if (rawId === undefined || rawId === null || String(rawId).trim() === '') return null;
  const id = Number(rawId);
  if (!Number.isInteger(id) || id <= 0) return { error: 'Invalid attachment upload id' };
  const upload = uploadRepository.findById(courseId, id);
  if (!upload) return { error: 'Attachment upload not found' };
  return id;
}

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
  const assignment = assignmentRepository.findById(courseId, assignmentId);
  if (!assignment) return res.status(404).send('Assignment not found');

  const attachmentId = validateAttachmentUploadId(courseId, attachment_upload_id);
  if (attachmentId && attachmentId.error) {
    const course = courseRepository.findById(courseId);
    return res.status(400).render('submission_new', { course, assignment, error: attachmentId.error });
  }
  const submission = submissionRepository.createSubmission({
    assignment_id: assignmentId,
    course_id: courseId,
    student_id: req.currentUser.user_id,
    content_text,
    attachment_upload_id: attachmentId || null
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

  const attachmentId = validateAttachmentUploadId(courseId, attachment_upload_id);
  if (attachmentId && attachmentId.error) {
    return res.status(400).json({ success: false, error: attachmentId.error });
  }
  const submission = submissionRepository.createSubmission({
    assignment_id: assignmentId,
    course_id: courseId,
    student_id: req.currentUser.user_id,
    content_text,
    attachment_upload_id: attachmentId || null
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

  const attachmentId = validateAttachmentUploadId(courseId, attachment_upload_id);
  if (attachmentId && attachmentId.error) {
    return res.status(400).send(attachmentId.error);
  }
  submissionRepository.updateSubmission(courseId, assignmentId, submissionId, {
    content_text,
    attachment_upload_id: attachmentId || null
  });
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

  const attachmentId = validateAttachmentUploadId(courseId, attachment_upload_id);
  if (attachmentId && attachmentId.error) {
    return res.status(400).json({ success: false, error: attachmentId.error });
  }
  const updated = submissionRepository.updateSubmission(courseId, assignmentId, submissionId, {
    content_text,
    attachment_upload_id: attachmentId || null
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