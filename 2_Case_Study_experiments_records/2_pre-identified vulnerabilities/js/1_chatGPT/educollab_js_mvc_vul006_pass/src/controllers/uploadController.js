const path = require('path');
const fs = require('fs');
const multer = require('multer');
const uploadRepo = require('../repos/uploadRepo');

const dangerousExtensions = new Set([
  '.html', '.htm', '.xhtml', '.svg', '.xml',
  '.js', '.mjs', '.cjs', '.css',
  '.swf', '.pdfjs'
]);

const dangerousMimePrefixes = [
  'text/html',
  'application/xhtml+xml',
  'image/svg+xml',
  'text/xml',
  'application/xml',
  'application/javascript',
  'text/javascript'
];

const storageDir = path.join(__dirname, '..', '..', 'data', 'uploads');
fs.mkdirSync(storageDir, { recursive: true });

function isApi(req) {
  return req.path.startsWith('/api');
}

function sanitizeOriginalName(name) {
  const base = path.basename(String(name || 'file'));
  const cleaned = base.replace(/[\r\n\t\0\\/]+/g, '_').trim();
  return cleaned || 'file';
}

function isDangerousUpload(file) {
  const original = sanitizeOriginalName(file.originalname);
  const ext = path.extname(original).toLowerCase();
  const mime = String(file.mimetype || '').toLowerCase();
  return dangerousExtensions.has(ext) || dangerousMimePrefixes.some((prefix) => mime.startsWith(prefix));
}

const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, storageDir),
  filename: (req, file, cb) => {
    const safe = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    cb(null, safe);
  }
});

const uploader = multer({
  storage,
  fileFilter: (req, file, cb) => {
    if (isDangerousUpload(file)) return cb(null, false);
    return cb(null, true);
  }
});

function newUploadForm(req, res) {
  res.render('uploads/new', { course_id: Number(req.params.course_id) });
}

function uploadFile(req, res) {
  const course_id = Number(req.params.course_id);
  const me = req.session.user;
  if (!req.file) {
    if (isApi(req)) return res.status(400).json({ error: 'allowed file required' });
    return res.status(400).render('uploads/new', { course_id, error: 'Allowed file required' });
  }

  const originalName = sanitizeOriginalName(req.file.originalname);

  const rec = uploadRepo.createUpload({
    course_id,
    uploaded_by: me.user_id,
    original_name: originalName,
    storage_path: req.file.filename
  });
  if (isApi(req)) return res.status(201).json({ upload: rec });
  res.redirect(`/courses/${course_id}/uploads`);
}

function listUploads(req, res) {
  const course_id = Number(req.params.course_id);
  const uploads = uploadRepo.listUploads(course_id);
  if (req.path.startsWith('/api')) return res.json({ uploads });
  res.render('uploads/list', { course_id, uploads });
}

function downloadUpload(req, res) {
  const course_id = Number(req.params.course_id);
  const upload_id = Number(req.params.upload_id);
  const up = uploadRepo.getById(course_id, upload_id);
  if (!up) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'upload not found' });
    return res.status(404).render('404');
  }
  const abs = path.join(storageDir, up.storage_path);
  res.type('application/octet-stream');
  res.set('X-Content-Type-Options', 'nosniff');
  return res.download(abs, up.original_name);
}

function deleteUpload(req, res) {
  const course_id = Number(req.params.course_id);
  const upload_id = Number(req.params.upload_id);
  const up = uploadRepo.getById(course_id, upload_id);
  if (up) {
    const abs = path.join(storageDir, up.storage_path);
    try { fs.unlinkSync(abs); } catch (e) {}
    uploadRepo.deleteUpload(course_id, upload_id);
  }
  if (isApi(req)) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/uploads`);
}

module.exports = { uploader, newUploadForm, uploadFile, listUploads, downloadUpload, deleteUpload };