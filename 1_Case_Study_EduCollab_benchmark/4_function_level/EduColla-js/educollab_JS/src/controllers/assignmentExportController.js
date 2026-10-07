
const path = require('path');
const fs = require('fs');
const archiver = require('archiver');

const submissionRepo = require('../repos/submissionRepo');

const dataDir = path.join(__dirname, '..', '..', 'data');
const extractedRoot = path.join(dataDir, 'submissions_extracted');
const exportsRoot = path.join(dataDir, 'exports');
fs.mkdirSync(exportsRoot, { recursive: true });

function safeExportId() {
  return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function exportFilePath(export_id) {
  // Only allow strict pattern to prevent traversal
  if (!/^[0-9]{10,}-[a-f0-9]+$/.test(export_id)) return null;
  const p = path.join(exportsRoot, `submissions_${export_id}.zip`);
  const normalized = path.normalize(p);
  if (!normalized.startsWith(exportsRoot + path.sep) && normalized !== exportsRoot) return null;
  return normalized;
}


function exportAssignmentSubmissionsZip(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);

  const assignmentDir = path.join(extractedRoot, `course_${course_id}`, `assignment_${assignment_id}`);
  fs.mkdirSync(assignmentDir, { recursive: true });

  const export_id = safeExportId();
  const outPath = exportFilePath(export_id);
  const output = fs.createWriteStream(outPath);

  const archive = archiver('zip', { zlib: { level: 9 } });
  archive.on('error', (err) => {
    try { output.close(); } catch(e) {}
  });
  archive.pipe(output);

  // If extracted files exist, include them. Otherwise include a manifest listing submissions.
  if (fs.existsSync(assignmentDir)) {
    archive.directory(assignmentDir, false);
  }

  archive.finalize();

  output.on('close', () => {
    if (req.originalUrl.startsWith('/api')) {
      return res.json({ ok: true, export_id, download_url: `/api/courses/${course_id}/assignments/${assignment_id}/submissions/export/${export_id}/download` });
    }
    res.redirect(`/courses/${course_id}/assignments/${assignment_id}/submissions/export/${export_id}/download`);
  });
}


function downloadExportedSubmissionsZip(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const export_id = String(req.params.export_id || '');
  const p = exportFilePath(export_id);

  if (!p || !fs.existsSync(p)) {
    if (req.originalUrl.startsWith('/api')) return res.status(404).json({ error: 'export not found' });
    return res.status(404).render('404');
  }
  res.setHeader('Content-Type', 'application/zip');
  res.setHeader('Content-Disposition', `attachment; filename="course_${course_id}_assignment_${assignment_id}_submissions.zip"`);
  return res.sendFile(p);
}

module.exports = { exportAssignmentSubmissionsZip, downloadExportedSubmissionsZip };
