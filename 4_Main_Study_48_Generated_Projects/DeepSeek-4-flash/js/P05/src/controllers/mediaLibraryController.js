import fs from 'node:fs';
import path from 'node:path';
import env from '../config/env.js';
import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';
import { broadcast } from '../services/realTime.js';
import { audit } from '../services/audit.js';

function mediaSelect() {
  return `
    SELECT m.*, s.original_name, s.storage_name, s.mime_type, s.size_bytes, s.kind,
           '/api/cms/media_library/' || m.id || '/file' AS url,
           u.username AS uploader_username
    FROM media_library m
    JOIN stored_file s ON s.id = m.stored_file_id
    LEFT JOIN users u ON u.id = m.uploaded_by`;
}

function canManageMedia(user, media) {
  return ['admin'].includes(user.role_name) || media.uploaded_by === user.id;
}

export async function listMediaLibrary(req, res, next) {
  try {
    const db = getDb();
    const { q, visibility, page = 1, pageSize = 50 } = req.query;
    const params = { userId: req.user.id, limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) };
    const clauses = [];
    if (req.user.role_name === 'author') {
      clauses.push('(m.uploaded_by = @userId OR m.visibility = \'public\')');
    }
    if (visibility === 'public' || visibility === 'private') {
      clauses.push('m.visibility = @visibility');
      params.visibility = visibility;
    }
    if (q) {
      clauses.push('(s.original_name LIKE @q OR m.alt_text LIKE @q)');
      params.q = `%${q}%`;
    }
    const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
    const count = db.prepare(`SELECT COUNT(*) AS c FROM media_library m JOIN stored_file s ON s.id = m.stored_file_id ${where}`).get(params).c;
    const rows = db.prepare(`${mediaSelect()} ${where} ORDER BY m.id DESC LIMIT @limit OFFSET @offset`).all(params);
    return ok(res, { data: rows, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total: count } }, 'Media library.');
  } catch (err) {
    return next(err);
  }
}

export async function createMediaLibrary(req, res, next) {
  try {
    const db = getDb();
    const file = req.file;
    if (!file) return next(apiError(400, 'FILE_REQUIRED', 'A file upload is required.'));

    const body = req.body || {};
    const errors = validate(body, {
      altText: { type: 'string', maxLength: 300, label: 'Alt text' },
      visibility: { type: 'string', oneOf: ['public', 'private'], label: 'Visibility' }
    });
    if (hasErrors(errors)) {
      fs.rmSync(file.path, { force: true });
      return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));
    }

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const sfId = db.prepare(`INSERT INTO stored_file (original_name, storage_name, mime_type, size_bytes, owner_id, kind, created_at)
      VALUES (?, ?, ?, ?, ?, 'media', ?)`)
      .run(file.originalname || file.filename, file.filename, file.mimetype, file.size, req.user.id, now).lastInsertRowid;

    const mediaId = db.prepare(`INSERT INTO media_library (stored_file_id, alt_text, visibility, uploaded_by, created_at)
      VALUES (?, ?, ?, ?, ?)`)
      .run(sfId, body.altText || file.originalname || 'media', body.visibility === 'private' ? 'private' : 'public', req.user.id, now).lastInsertRowid;

    audit(db, { actorId: req.user.id, action: 'upload_media', entityType: 'media_library', entityId: mediaId, details: `Uploaded ${file.originalname}` });
    broadcast('media:uploaded', { mediaId, originalName: file.originalname, uploadedBy: req.user.username });

    const media = db.prepare(`${mediaSelect()} WHERE m.id = ?`).get(mediaId);
    return ok(res, { data: media }, 'File uploaded to the media library.', 201);
  } catch (err) {
    if (req.file && req.file.path) fs.rmSync(req.file.path, { force: true });
    return next(err);
  }
}

export async function patchMediaLibrary(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const media = db.prepare(`${mediaSelect()} WHERE m.id = ?`).get(id);
    if (!media) return next(apiError(404, 'NOT_FOUND', 'Media asset not found.'));
    if (!canManageMedia(req.user, media)) return next(apiError(403, 'FORBIDDEN', 'You do not have permission to modify this media asset.'));

    const body = req.body || {};
    const errors = validate(body, {
      altText: { type: 'string', maxLength: 300, label: 'Alt text' },
      visibility: { type: 'string', oneOf: ['public', 'private'], label: 'Visibility' },
      originalName: { type: 'string', maxLength: 200, label: 'File name' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    if (body.altText !== undefined) {
      db.prepare('UPDATE media_library SET alt_text = ? WHERE id = ?').run(body.altText, id);
    }
    if (body.visibility !== undefined) {
      db.prepare('UPDATE media_library SET visibility = ? WHERE id = ?').run(body.visibility, id);
    }
    if (body.originalName !== undefined && body.originalName.trim()) {
      db.prepare('UPDATE stored_file SET original_name = ? WHERE id = ?').run(body.originalName.trim(), media.stored_file_id);
    }
    db.prepare('UPDATE stored_file SET mime_type = ? WHERE id = ?').run(body.mimeType || media.mime_type, media.stored_file_id);

    broadcast('media:updated', { mediaId: id });
    const updated = db.prepare(`${mediaSelect()} WHERE m.id = ?`).get(id);
    return ok(res, { data: updated }, 'Media asset updated.');
  } catch (err) {
    return next(err);
  }
}

export async function deleteMediaLibrary(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const media = db.prepare(`${mediaSelect()} WHERE m.id = ?`).get(id);
    if (!media) return next(apiError(404, 'NOT_FOUND', 'Media asset not found.'));
    if (!canManageMedia(req.user, media)) return next(apiError(403, 'FORBIDDEN', 'You do not have permission to delete this media asset.'));

    db.prepare('DELETE FROM media_library WHERE id = ?').run(id);
    const storagePath = path.join(env.uploadDir, media.storage_name);
    fs.rmSync(storagePath, { force: true });
    db.prepare('DELETE FROM stored_file WHERE id = ?').run(media.stored_file_id);

    audit(db, { actorId: req.user.id, action: 'delete_media', entityType: 'media_library', entityId: id, details: `Deleted ${media.original_name}` });
    broadcast('media:deleted', { mediaId: id });
    return ok(res, { data: { id, deleted: true } }, 'Media asset deleted.');
  } catch (err) {
    return next(err);
  }
}

export async function streamMediaFile(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const media = db.prepare(`${mediaSelect()} WHERE m.id = ?`).get(id);
    if (!media) return next(apiError(404, 'NOT_FOUND', 'Media asset not found.'));
    if (media.visibility === 'private' && !canManageMedia(req.user, media)) {
      return next(apiError(403, 'FORBIDDEN', 'You do not have permission to access this private file.'));
    }
    const filePath = path.join(env.uploadDir, media.storage_name);
    if (!fs.existsSync(filePath)) return next(apiError(404, 'NOT_FOUND', 'Media file is missing on disk.'));

    res.setHeader('Content-Type', media.mime_type || 'application/octet-stream');
    res.setHeader('Content-Length', media.size_bytes);
    res.setHeader('Cache-Control', 'public, max-age=86400');
    res.setHeader('Content-Disposition', req.params.download ? `attachment; filename="${media.original_name}"` : 'inline');
    return fs.createReadStream(filePath).pipe(res);
  } catch (err) {
    return next(err);
  }
}
