import fs from 'node:fs';
import path from 'node:path';
import env from '../config/env.js';
import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';
import { exportData, importData } from '../services/importExport.js';
import { audit } from '../services/audit.js';
import { broadcast } from '../services/realTime.js';

function importExportSelect() {
  return `
    SELECT ie.*, s.original_name AS file_name, s.mime_type AS file_mime_type, s.size_bytes AS file_size,
           u.username AS requested_by_username
    FROM import_export ie
    LEFT JOIN stored_file s ON s.id = ie.file_id
    LEFT JOIN users u ON u.id = ie.requested_by`;
}

export async function listImportExport(req, res, next) {
  try {
    const db = getDb();
    const { kind, page = 1, pageSize = 50 } = req.query;
    const params = { limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) };
    const where = kind === 'import' || kind === 'export' ? 'WHERE ie.kind = @kind' : '';
    if (kind === 'import' || kind === 'export') params.kind = kind;
    const total = db.prepare(`SELECT COUNT(*) AS c FROM import_export ie ${where}`).get(params).c;
    const rows = db.prepare(`${importExportSelect()} ${where} ORDER BY ie.id DESC LIMIT @limit OFFSET @offset`).all(params);
    return ok(res, { data: rows, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total } }, 'Import/export history.');
  } catch (err) {
    return next(err);
  }
}

export async function createImportExport(req, res, next) {
  try {
    const db = getDb();
    const isMultipart = req.file !== undefined;
    if (isMultipart) {
      const body = req.body || {};
      const result = importData({
        filePath: req.file.path,
        originalName: req.file.originalname || req.file.filename,
        requestedBy: req.user.id
      });
      audit(db, { actorId: req.user.id, action: 'import', entityType: 'import_export', entityId: result.id, details: `Imported ${result.totalRecords} articles (${result.summary.articlesCreated} new)` });
      broadcast('import_export:done', { id: result.id, kind: 'import' });
      return ok(res, { data: result }, `Import completed: ${result.summary.articlesCreated} new articles imported.`, 201);
    }

    // JSON export request
    const body = req.body || {};
    const errors = validate(body, {
      kind: { type: 'string', label: 'Kind' },
      format: { type: 'string', oneOf: ['json', 'csv'], label: 'Format' },
      scope: { type: 'string', maxLength: 80, label: 'Scope' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));
    if (body.kind === 'import') {
      return next(apiError(400, 'IMPORT_FILE_REQUIRED', 'Import requires a multipart file upload.'));
    }

    const result = exportData({
      format: body.format || 'json',
      scope: body.scope || 'full',
      requestedBy: req.user.id
    });
    audit(db, { actorId: req.user.id, action: 'export', entityType: 'import_export', entityId: result.id, details: `Exported ${result.recordCount} articles as ${result.format}` });
    broadcast('import_export:done', { id: result.id, kind: 'export' });

    const record = db.prepare(`${importExportSelect()} WHERE ie.id = ?`).get(result.id);
    return ok(res, { data: record }, `Export created: ${result.filename}.`, 201);
  } catch (err) {
    if (req.file && req.file.path) fs.rmSync(req.file.path, { force: true });
    return next(err);
  }
}

export async function patchImportExport(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const existing = db.prepare('SELECT * FROM import_export WHERE id = ?').get(id);
    if (!existing) return next(apiError(404, 'NOT_FOUND', 'Import/export record not found.'));

    const body = req.body || {};
    if (body.scope !== undefined) {
      if (typeof body.scope !== 'string' || body.scope.length > 80) {
        return next(apiError(400, 'VALIDATION_ERROR', 'Scope must be a string of at most 80 characters.'));
      }
      db.prepare('UPDATE import_export SET scope = ? WHERE id = ?').run(body.scope, id);
    }
    const record = db.prepare(`${importExportSelect()} WHERE ie.id = ?`).get(id);
    return ok(res, { data: record }, 'Import/export record updated.');
  } catch (err) {
    return next(err);
  }
}

export async function downloadImportExport(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const record = db.prepare(`
      SELECT ie.*, s.storage_name, s.original_name, s.mime_type, s.size_bytes
      FROM import_export ie
      LEFT JOIN stored_file s ON s.id = ie.file_id
      WHERE ie.id = ?
    `).get(id);
    if (!record) return next(apiError(404, 'NOT_FOUND', 'Import/export record not found.'));
    if (!record.storage_name) return next(apiError(404, 'NOT_FOUND', 'No file is attached to this record.'));

    const filePath = path.join(record.kind === 'import' ? env.uploadDir : env.exportDir, record.storage_name);
    if (!fs.existsSync(filePath)) return next(apiError(404, 'NOT_FOUND', 'File is missing on disk.'));

    res.setHeader('Content-Type', record.mime_type || 'application/octet-stream');
    res.setHeader('Content-Disposition', `attachment; filename="${record.original_name}"`);
    return fs.createReadStream(filePath).pipe(res);
  } catch (err) {
    return next(err);
  }
}
