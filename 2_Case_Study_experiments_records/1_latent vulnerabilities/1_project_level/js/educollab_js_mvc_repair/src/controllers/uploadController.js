const path = require('path');
const fs = require('fs');
const multer = require('multer');
const uploadRepo = require('../repos/uploadRepo');

const storageDir = path.join(__dirname, '..', '..', 'data', 'uploads');
fs.mkdirSync(storageDir, { recursive: true });

const allowedExt = new Set(['.txt', '.pdf', '.png', '.jpg', '.jpeg', '.gif', '.zip', '.csv', '.md']);

function safeBasename(name) {
  const base = path.basename(String(name || ''));
  return base.replace(/[^A-Za-z0-9._-]/g, '_').slice(0, 200) || 'file';
}

function isWithin(child, parent) {
  const rel = path.relative(parent, child);
  return rel && !rel.startsWith('..') && !path.isAbsolute(rel);
}

const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, storageDir),
  filename: (req, file, cb) => {
    const original = safeBasename(file.originalname);
    const safe = `${Date.now()}-${Math.random().toString(16).slice(2)}-${original}`;
    cb(null, safe);
  }
});

const uploader = multer({
  storage,
  fileFilter: (req, file, cb) => {
    const original = safeBasename(file.originalname);
    const ext = path.extname(original).toLowerCase();
    if (!allowedExt.has(ext)) return cb(null, false);
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
    if (req.path.startsWith('/api')) {
      return res.status(400).json({ error: 'file required or type not allowed' });
    }
    return res.status(400).render('uploads/new', { course_id, error: 'File required (or type not allowed)' });
  }
  const rec = uploadRepo.createUpload({
    course_id,
    uploaded_by: me.user_id,
    original_name: safeBasename(req.file.originalname),
    storage_path: req.file.filename
  });
  if (req.path.startsWith('/api')) return res.status(201).json({ upload: rec });
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

  const abs = path.resolve(storageDir, up.storage_path);
  if (!isWithin(abs, storageDir)) {
    if (req.path.startsWith('/api')) return res.status(404).json({ error: 'upload not found' });
    return res.status(404).render('404');
  }

  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Content-Type', 'application/octet-stream');
  return res.download(abs, up.original_name);
}

function deleteUpload(req, res) {
  const course_id = Number(req.params.course_id);
  const upload_id = Number(req.params.upload_id);
  const up = uploadRepo.getById(course_id, upload_id);
  if (up) {
    const abs = path.resolve(storageDir, up.storage_path);
    if (isWithin(abs, storageDir)) {
      try {
        fs.unlinkSync(abs);
      } catch (e) {}
    }
    uploadRepo.deleteUpload(course_id, upload_id);
  }
  if (req.path.startsWith('/api')) return res.json({ ok: true });
  res.redirect(`/courses/${course_id}/uploads`);
}

module.exports = { uploader, newUploadForm, uploadFile, listUploads, downloadUpload, deleteUpload };