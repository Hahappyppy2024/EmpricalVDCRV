const submissionRepo = require('../repos/submissionRepo');

function submitForm(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const me = req.session.user;
  const existing = submissionRepo.getMySubmission(course_id, assignment_id, me.user_id);
  res.render('submissions/submit', { course_id, assignment_id, existing });
}

function createSubmission(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const me = req.session.user;
  const { content_text, attachment_upload_id } = req.body;

  const existing = submissionRepo.getMySubmission(course_id, assignment_id, me.user_id);
  if (existing) {
    // if already exists, update it
    const updated = submissionRepo.updateSubmission(course_id, assignment_id, existing.submission_id, { content_text, attachment_upload_id });
    if (req.path.startsWith('/api')) return res.json({ submission: updated });
    return res.redirect(`/courses/${course_id}/assignments/${assignment_id}`);
  }

  const s = submissionRepo.createSubmission({
    assignment_id,
    course_id,
    student_id: me.user_id,
    content_text: content_text || '',
    attachment_upload_id
  });
  if (req.path.startsWith('/api')) return res.status(201).json({ submission: s });
  res.redirect(`/courses/${course_id}/assignments/${assignment_id}`);
}

function updateMySubmission(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const submission_id = Number(req.params.submission_id);
  const { content_text, attachment_upload_id } = req.body;
  const submission = submissionRepo.updateSubmission(course_id, assignment_id, submission_id, { content_text, attachment_upload_id });
  if (req.path.startsWith('/api')) return res.json({ submission });
  res.redirect(`/courses/${course_id}/assignments/${assignment_id}`);
}

function listMySubmissions(req, res) {
  const me = req.session.user;
  const submissions = submissionRepo.listMySubmissions(me.user_id);
  if (req.path.startsWith('/api')) return res.json({ submissions });
  res.render('submissions/my', { submissions });
}

function listForAssignment(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const submissions = submissionRepo.listForAssignment(course_id, assignment_id);
  if (req.path.startsWith('/api')) return res.json({ submissions });
  res.render('submissions/list', { course_id, assignment_id, submissions });
}

module.exports = {
  submitForm,
  createSubmission,
  updateMySubmission,
  listMySubmissions,
  listForAssignment
};
