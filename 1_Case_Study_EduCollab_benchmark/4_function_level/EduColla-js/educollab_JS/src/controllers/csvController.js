
const { stringify } = require('csv-stringify/sync');
const { parse } = require('csv-parse/sync');
const multer = require('multer');
const path = require('path');
const fs = require('fs');

const membershipRepo = require('../repos/membershipRepo');
const userRepo = require('../repos/userRepo');
const submissionRepo = require('../repos/submissionRepo');

const dataDir = path.join(__dirname, '..', '..', 'data');
const tmpDir = path.join(dataDir, 'tmp');
fs.mkdirSync(tmpDir, { recursive: true });

const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, tmpDir),
  filename: (req, file, cb) => cb(null, `${Date.now()}-${Math.random().toString(16).slice(2)}-${file.originalname.replace(/[^a-zA-Z0-9._-]/g, '_')}`)
});
const uploader = multer({ storage, limits: { fileSize: 2 * 1024 * 1024 } }); // 2MB

function csvSafeCell(v) {
  const s = String(v ?? '');
  if (/^[=+\-@]/.test(s)) return "'" + s;
  return s;
}

function exportMembersCsv(req, res) {
  const course_id = Number(req.params.course_id);
  const rows = membershipRepo.listMembers(course_id).map(m => ({
    user_id: m.user_id,
    username: csvSafeCell(m.username),
    display_name: csvSafeCell(m.display_name),
    role_in_course: m.role_in_course
  }));

  const csv = stringify(rows, { header: true, columns: ['user_id', 'username', 'display_name', 'role_in_course'] });
  res.setHeader('Content-Type', 'text/csv; charset=utf-8');
  res.setHeader('Content-Disposition', `attachment; filename="course_${course_id}_members.csv"`);
  return res.send(csv);
}

function importMembersForm(req, res) {
  const course_id = Number(req.params.course_id);
  res.render('members/import_csv', { course_id });
}

function importMembersCsv(req, res) {
  const course_id = Number(req.params.course_id);
  if (!req.file) {
    if (req.originalUrl.startsWith('/api')) return res.status(400).json({ error: 'csv required' });
    return res.status(400).render('members/import_csv', { course_id, error: 'CSV file required' });
  }

  const buf = fs.readFileSync(req.file.path);
  let records;
  try {
    records = parse(buf, { columns: true, skip_empty_lines: true, trim: true });
  } catch (e) {
    if (req.originalUrl.startsWith('/api')) return res.status(400).json({ error: 'invalid csv' });
    return res.status(400).render('members/import_csv', { course_id, error: 'Invalid CSV' });
  } finally {
    try { fs.unlinkSync(req.file.path); } catch (e) {}
  }

  const allowedRoles = new Set(['admin', 'teacher', 'student', 'assistant', 'senior-assistant']);
  let createdUsers = 0, addedMemberships = 0, updatedRoles = 0;

  for (const r of records) {
    const username = String(r.username || '').trim();
    const display_name = String(r.display_name || username).trim();
    const role = String(r.role_in_course || 'student').trim();

    if (!username) continue;
    if (!allowedRoles.has(role)) continue;

    let u = userRepo.getByUsername(username);
    if (!u) {
      // Imported roster users are created as students by default password = 'password'
      u = userRepo.createUserWithPassword({ username, password: 'password', display_name });
      createdUsers++;
    }
    const existing = membershipRepo.getByCourseAndUser(course_id, u.user_id);
    if (!existing) {
      membershipRepo.addMember({ course_id, user_id: u.user_id, role_in_course: role });
      addedMemberships++;
    } else if (existing.role_in_course !== role) {
      membershipRepo.updateRole(existing.membership_id, role);
      updatedRoles++;
    }
  }

  const result = { ok: true, createdUsers, addedMemberships, updatedRoles };
  if (req.originalUrl.startsWith('/api')) return res.json(result);
  res.redirect(`/courses/${course_id}/members`);
}

function exportAssignmentGradesCsv(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);

  const subs = submissionRepo.listForAssignment(course_id, assignment_id);
  const rows = subs.map(s => ({
    submission_id: s.submission_id,
    student_id: s.student_id,
    username: csvSafeCell(s.student_username),
    score: s.score ?? '',
    feedback: csvSafeCell(s.feedback ?? '')
  }));

  const csv = stringify(rows, { header: true, columns: ['submission_id', 'student_id', 'username', 'score', 'feedback'] });
  res.setHeader('Content-Type', 'text/csv; charset=utf-8');
  res.setHeader('Content-Disposition', `attachment; filename="course_${course_id}_assignment_${assignment_id}_grades.csv"`);
  return res.send(csv);
}



function importAssignmentGradesForm(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  res.render('assignments/grades_import', { course_id, assignment_id });
}

function importAssignmentGradesCsv(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);

  if (!req.file) {
    if (req.originalUrl.startsWith('/api')) return res.status(400).json({ error: 'csv required' });
    return res.status(400).render('assignments/grades_import', { course_id, assignment_id, error: 'CSV file required' });
  }

  const buf = fs.readFileSync(req.file.path);
  let records;
  try {
    records = parse(buf, { columns: true, skip_empty_lines: true, trim: true });
  } catch (e) {
    if (req.originalUrl.startsWith('/api')) return res.status(400).json({ error: 'invalid csv' });
    return res.status(400).render('assignments/grades_import', { course_id, assignment_id, error: 'Invalid CSV' });
  } finally {
    try { fs.unlinkSync(req.file.path); } catch (e) {}
  }

  let updatedCount = 0;
  for (const r of records) {
    const submission_id = Number(r.submission_id);
    if (!submission_id) continue;

    let score = r.score;
    if (score === '' || score === null || typeof score === 'undefined') score = null;
    else {
      const n = Number(score);
      if (!Number.isFinite(n)) continue;
      score = Math.max(0, Math.min(100, Math.round(n)));
    }

    const feedback = String(r.feedback ?? '');

    const updated = submissionRepo.updateGrade(course_id, assignment_id, submission_id, { score, feedback });
    if (updated) updatedCount++;
  }

  const result = { ok: true, updatedCount };
  if (req.originalUrl.startsWith('/api')) return res.json(result);
  res.redirect(`/courses/${course_id}/assignments/${assignment_id}/submissions`);
}

module.exports = { uploader, exportMembersCsv, importMembersForm, importMembersCsv, exportAssignmentGradesCsv, importAssignmentGradesForm, importAssignmentGradesCsv };
