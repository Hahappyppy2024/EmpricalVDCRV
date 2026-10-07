
const path = require('path');
const fs = require('fs');
const unzipper = require('unzipper');

const submissionRepo = require('../repos/submissionRepo');
const uploadRepo = require('../repos/uploadRepo');

const dataDir = path.join(__dirname, '..', '..', 'data');
const uploadsDir = path.join(dataDir, 'uploads');
const extractedRoot = path.join(dataDir, 'submissions_extracted');
fs.mkdirSync(extractedRoot, { recursive: true });

console.log('[zip] submissionRepo keys =', Object.keys(submissionRepo));


function submissionExtractDir(course_id, assignment_id, submission_id) {
  const p = path.join(extractedRoot, `course_${course_id}`, `assignment_${assignment_id}`, `submission_${submission_id}`);
  fs.mkdirSync(p, { recursive: true });
  return p;
}

async function unzipSubmissionAttachment(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const submission_id = Number(req.params.submission_id);

  const s = submissionRepo.getById(course_id, assignment_id, submission_id);
  
  if (!s) {
    if (req.originalUrl.startsWith('/api')) return res.status(404).json({ error: 'submission not found' });
    return res.status(404).render('404');
  }
  if (!s.attachment_upload_id) {
    if (req.originalUrl.startsWith('/api')) return res.status(400).json({ error: 'no attachment on submission' });
    return res.status(400).send('No attachment');
  }

  const up = uploadRepo.getById(course_id, Number(s.attachment_upload_id));
  if (!up) {
    if (req.originalUrl.startsWith('/api')) return res.status(404).json({ error: 'attachment upload not found' });
    return res.status(404).render('404');
  }

  const lower = (up.original_name || '').toLowerCase();
  if (!lower.endsWith('.zip')) {
    if (req.originalUrl.startsWith('/api')) return res.status(400).json({ error: 'attachment is not a zip' });
    return res.status(400).send('Attachment is not a zip');
  }


  const zipPath = path.join(uploadsDir, up.storage_path);
  if (!fs.existsSync(zipPath)) {
    if (req.originalUrl.startsWith('/api')) return res.status(404).json({ error: 'zip file missing' });
    return res.status(404).render('404');
  }

  const targetDir = submissionExtractDir(course_id, assignment_id, submission_id);


  const directory = await unzipper.Open.file(zipPath);
  let extractedCount = 0;

  for (const entry of directory.files) {
    if (entry.type !== 'File') continue;

    const cleaned = entry.path.replace(/^([/\\])+/, '');
    const dest = path.join(targetDir, cleaned);
    const normalized = path.normalize(dest);

    if (!normalized.startsWith(targetDir + path.sep) && normalized !== targetDir) {
      continue; // suspicious entry
    }
    fs.mkdirSync(path.dirname(normalized), { recursive: true });

    await new Promise((resolve, reject) => {
      entry.stream()
        .pipe(fs.createWriteStream(normalized))
        .on('finish', resolve)
        .on('error', reject);
    });
    extractedCount++;
  }

  if (req.originalUrl.startsWith('/api')) return res.json({ ok: true, extractedCount });
  res.redirect(`/courses/${course_id}/assignments/${assignment_id}/submissions/${submission_id}/files`);
}


function listSubmissionExtractedFiles(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const submission_id = Number(req.params.submission_id);
  const base = submissionExtractDir(course_id, assignment_id, submission_id);

  const files = [];
  function walk(dir) {
    for (const name of fs.readdirSync(dir)) {
      const abs = path.join(dir, name);
      const st = fs.statSync(abs);
      if (st.isDirectory()) walk(abs);
      else files.push({
        path: path.relative(base, abs).replace(/\\/g, '/'),
        size: st.size,
        mtime: st.mtime.toISOString()
      });
    }
  }
  walk(base);

  if (req.originalUrl.startsWith('/api')) return res.json({ files });
  res.render('submissions/files', { course_id, assignment_id, submission_id, files });
}

module.exports = { unzipSubmissionAttachment, listSubmissionExtractedFiles };
