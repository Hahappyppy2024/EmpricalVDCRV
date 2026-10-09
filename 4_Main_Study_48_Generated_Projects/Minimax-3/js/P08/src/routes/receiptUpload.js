import express from 'express';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import multer from 'multer';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';

const router = express.Router();

const STORAGE_DIR = process.env.RECEIPT_STORAGE_DIR
  ? path.resolve(process.cwd(), process.env.RECEIPT_STORAGE_DIR)
  : path.resolve(process.cwd(), 'storage/receipts');

if (!fs.existsSync(STORAGE_DIR)) {
  fs.mkdirSync(STORAGE_DIR, { recursive: true });
}

const ALLOWED_MIME = new Set([
  'image/png',
  'image/jpeg',
  'image/jpg',
  'image/webp',
  'application/pdf',
  'text/plain'
]);

const MAX_BYTES = Number(process.env.MAX_UPLOAD_BYTES || 5 * 1024 * 1024);

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, STORAGE_DIR),
  filename: (_req, file, cb) => {
    const ext = path.extname(file.originalname || '') || '';
    const safeExt = ext.replace(/[^a-zA-Z0-9.]/g, '').slice(0, 8);
    const name = crypto.randomBytes(16).toString('hex') + safeExt;
    cb(null, name);
  }
});

const upload = multer({
  storage,
  limits: { fileSize: MAX_BYTES },
  fileFilter: (_req, file, cb) => {
    if (!ALLOWED_MIME.has(file.mimetype)) {
      return cb(new Error('Unsupported file type'));
    }
    cb(null, true);
  }
});

function ensureLineAccess(session, line) {
  if (!line) {
    const e = new Error('Line not found'); e.status = 404; throw e;
  }
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(line.report_id);
  if (!report) {
    const e = new Error('Report not found'); e.status = 404; throw e;
  }
  const user = session.user;
  if (user.role === 'admin' || user.role === 'finance') return { report, line };
  if (user.role === 'manager') {
    const owner = db.prepare('SELECT manager_id FROM users WHERE id = ?').get(report.employee_id);
    if (owner?.manager_id !== user.id && report.employee_id !== user.id) {
      const e = new Error('Not authorized'); e.status = 403; throw e;
    }
    return { report, line };
  }
  if (report.employee_id !== user.id) {
    const e = new Error('Not your line'); e.status = 403; throw e;
  }
  return { report, line };
}

router.get('/receipt_upload', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const files = db.prepare(`
    SELECT sf.*, u.username AS owner_username
    FROM stored_files sf
    JOIN users u ON u.id = sf.owner_id
    WHERE sf.context = 'receipt'
    ORDER BY sf.created_at DESC LIMIT 100
  `).all();
  return ok(res, { files });
});

router.get('/receipt_upload/file/:id', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const id = Number(req.params.id);
  const file = db.prepare('SELECT * FROM stored_files WHERE id = ?').get(id);
  if (!file) return fail(res, 'Not found', 404, 'not_found');
  const user = session.user;
  if (user.role !== 'admin' && user.role !== 'finance' && file.owner_id !== user.id) {
    if (user.role === 'manager') {
      const report = db.prepare('SELECT employee_id FROM expense_reports WHERE id = ?').get(file.related_report_id);
      const owner = report ? db.prepare('SELECT manager_id FROM users WHERE id = ?').get(report.employee_id) : null;
      if (!owner || owner.manager_id !== user.id) {
        return fail(res, 'Forbidden', 403, 'forbidden');
      }
    } else {
      return fail(res, 'Forbidden', 403, 'forbidden');
    }
  }
  if (!fs.existsSync(file.storage_path)) return fail(res, 'Stored file missing', 410, 'gone');
  res.setHeader('Content-Type', file.mime_type);
  res.setHeader('Content-Length', file.size_bytes);
  res.setHeader('Content-Disposition', `inline; filename="${file.filename}"`);
  fs.createReadStream(file.storage_path).pipe(res);
});

router.post('/receipt_upload', upload.single('file'), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (!req.file) return fail(res, 'file is required', 400, 'validation_error');

  const reportId = Number(req.body.reportId);
  const lineId = req.body.lineId ? Number(req.body.lineId) : null;
  if (!reportId) {
    fs.unlinkSync(req.file.path);
    return fail(res, 'reportId is required', 400, 'validation_error');
  }
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) {
    fs.unlinkSync(req.file.path);
    return fail(res, 'Report not found', 404, 'not_found');
  }
  const user = session.user;
  if (user.role !== 'admin' && report.employee_id !== user.id && !(user.role === 'manager')) {
    fs.unlinkSync(req.file.path);
    return fail(res, 'Not your report', 403, 'forbidden');
  }
  let line = null;
  if (lineId) {
    line = db.prepare('SELECT * FROM expense_lines WHERE id = ?').get(lineId);
    if (!line || line.report_id !== reportId) {
      fs.unlinkSync(req.file.path);
      return fail(res, 'Line does not belong to report', 400, 'validation_error');
    }
  }
  if (report.status !== 'draft') {
    fs.unlinkSync(req.file.path);
    return fail(res, `Cannot upload receipt for report in status ${report.status}`, 409, 'invalid_transition');
  }

  const info = db.prepare(`
    INSERT INTO stored_files (owner_id, filename, stored_name, mime_type, size_bytes, storage_path, context, related_report_id, related_line_id)
    VALUES (?, ?, ?, ?, ?, ?, 'receipt', ?, ?)
  `).run(user.id, req.file.originalname, req.file.filename, req.file.mimetype, req.file.size, req.file.path, reportId, lineId);

  if (lineId) {
    db.prepare('UPDATE expense_lines SET receipt_id = ? WHERE id = ?').run(info.lastInsertRowid, lineId);
  }
  db.prepare(`
    INSERT INTO activity_logs (actor_id, report_id, event_type, details)
    VALUES (?, ?, 'receipt_uploaded', ?)
  `).run(user.id, reportId, JSON.stringify({ fileId: info.lastInsertRowid, filename: req.file.originalname }));

  return ok(res, {
    fileId: info.lastInsertRowid,
    storedName: req.file.filename,
    filename: req.file.originalname,
    mimeType: req.file.mimetype,
    sizeBytes: req.file.size,
    relatedReportId: reportId,
    relatedLineId: lineId
  }, 201);
});

router.delete('/receipt_upload/:id', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const id = Number(req.params.id);
  const file = db.prepare('SELECT * FROM stored_files WHERE id = ? AND context = \'receipt\'').get(id);
  if (!file) return fail(res, 'Not found', 404, 'not_found');
  const user = session.user;
  if (file.owner_id !== user.id && user.role !== 'admin') {
    return fail(res, 'Cannot delete file you do not own', 403, 'forbidden');
  }
  try { if (fs.existsSync(file.storage_path)) fs.unlinkSync(file.storage_path); } catch (_) {}
  db.prepare('DELETE FROM stored_files WHERE id = ?').run(id);
  db.prepare('UPDATE expense_lines SET receipt_id = NULL WHERE receipt_id = ?').run(id);
  return ok(res, { deleted: true });
});

export default router;
