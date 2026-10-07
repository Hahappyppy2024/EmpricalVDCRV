
const path = require('path');
const fs = require('fs');
const multer = require('multer');
const unzipper = require('unzipper');
const archiver = require('archiver');
const uploadRepo = require('../repos/uploadRepo');

const dataDir = path.join(__dirname, '..', '..', 'data');
const zipStorageDir = path.join(dataDir, 'materials_zips');
const materialsRootDir = path.join(dataDir, 'materials');

fs.mkdirSync(zipStorageDir, { recursive: true });
fs.mkdirSync(materialsRootDir, { recursive: true });

function courseMaterialsDir(course_id) {
  const p = path.join(materialsRootDir, `course_${course_id}`);
  fs.mkdirSync(p, { recursive: true });
  return p;
}

const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, zipStorageDir),
  filename: (req, file, cb) => {
    const safe = `${Date.now()}-${Math.random().toString(16).slice(2)}-${file.originalname.replace(/[^a-zA-Z0-9._-]/g, '_')}`;
    cb(null, safe);
  }
});

const uploader = multer({
  storage,
  limits: { fileSize: 10 * 1024 * 1024 }, // 10MB
  fileFilter: (req, file, cb) => {
    const ok = file.mimetype === 'application/zip' || file.originalname.toLowerCase().endsWith('.zip');
    cb(ok ? null : new Error('zip only'), ok);
  }
});

function uploadZipForm(req, res) {
  const course_id = Number(req.params.course_id);
  res.render('materials/upload_zip', { course_id });
}

async function uploadZipExtract(req, res) {
  const course_id = Number(req.params.course_id);
  const me = req.session.user;

  if (!req.file) {
    if (req.originalUrl.startsWith('/api')) return res.status(400).json({ error: 'zip file required' });
    return res.status(400).render('materials/upload_zip', { course_id, error: 'ZIP file required' });
  }

  // Record the zip itself as an upload for traceability (optional)
  uploadRepo.createUpload({
    course_id,
    uploaded_by: me.user_id,
    original_name: req.file.originalname,
    storage_path: path.join('materials_zips', req.file.filename)
  });

  const targetDir = courseMaterialsDir(course_id);

  // Zip Slip protection: ensure each entry stays within targetDir after normalization
  const zipPath = path.join(zipStorageDir, req.file.filename);
  const directory = await unzipper.Open.file(zipPath);

  for (const entry of directory.files) {
    if (entry.type !== 'File') continue;
    const cleaned = entry.path.replace(/^([/\\])+/, '');
    const destPath = path.join(targetDir, cleaned);
    const normalized = path.normalize(destPath);
    if (!normalized.startsWith(targetDir + path.sep) && normalized !== targetDir) {
      // skip suspicious entry
      continue;
    }
    fs.mkdirSync(path.dirname(normalized), { recursive: true });
    await new Promise((resolve, reject) => {
      entry.stream()
        .pipe(fs.createWriteStream(normalized))
        .on('finish', resolve)
        .on('error', reject);
    });
  }

  if (req.originalUrl.startsWith('/api')) return res.status(201).json({ ok: true });
  res.redirect(`/courses/${course_id}/materials/files`);
}

function listMaterialsFiles(req, res) {
  const course_id = Number(req.params.course_id);
  const base = courseMaterialsDir(course_id);

  const files = [];
  function walk(dir) {
    for (const name of fs.readdirSync(dir)) {
      const abs = path.join(dir, name);
      const st = fs.statSync(abs);
      if (st.isDirectory()) walk(abs);
      else {
        files.push({
          path: path.relative(base, abs).replace(/\\/g, '/'),
          size: st.size,
          mtime: st.mtime.toISOString()
        });
      }
    }
  }
  walk(base);

  if (req.originalUrl.startsWith('/api')) return res.json({ files });
  res.render('materials/files', { course_id, files });
}

function downloadMaterialsZip(req, res) {
  const course_id = Number(req.params.course_id);
  const base = courseMaterialsDir(course_id);

  res.setHeader('Content-Type', 'application/zip');
  res.setHeader('Content-Disposition', `attachment; filename="course_${course_id}_materials.zip"`);

  const archive = archiver('zip', { zlib: { level: 9 } });
  archive.on('error', (err) => {
    try { res.status(500).end(); } catch (e) {}
  });
  archive.pipe(res);
  archive.directory(base, false);
  archive.finalize();
}

module.exports = { uploader, uploadZipForm, uploadZipExtract, listMaterialsFiles, downloadMaterialsZip };
