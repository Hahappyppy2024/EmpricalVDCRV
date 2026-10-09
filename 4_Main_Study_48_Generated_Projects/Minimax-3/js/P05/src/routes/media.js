import { Router } from 'express';
import multer from 'multer';
import { existsSync, mkdirSync, writeFileSync, unlinkSync, statSync, renameSync } from 'node:fs';
import { join, extname, basename } from 'node:path';
import { getDb } from '../db/database.js';
import { requireAuth } from '../middleware/auth.js';
import { validationError, notFoundError, forbidError } from '../services/errors.js';
import { nanoid } from 'nanoid';

const router = Router();
const UPLOAD_DIR = process.env.UPLOAD_DIR || './uploads';
const MAX_BYTES = Number(process.env.MAX_UPLOAD_BYTES || 10485760);
const ALLOWED_MIME = new Set(['image/png', 'image/jpeg', 'image/svg+xml', 'image/gif', 'application/pdf']);

if (!existsSync(UPLOAD_DIR)) mkdirSync(UPLOAD_DIR, { recursive: true });

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, UPLOAD_DIR),
  filename: (_req, file, cb) => {
    const ext = extname(file.originalname).toLowerCase() || '';
    const safe = `${Date.now()}_${nanoid(8)}${ext}`;
    cb(null, safe);
  }
});
const upload = multer({
  storage,
  limits: { fileSize: MAX_BYTES },
  fileFilter: (_req, file, cb) => {
    if (!ALLOWED_MIME.has(file.mimetype)) return cb(new Error('invalid_mime'));
    cb(null, true);
  }
});

router.get('/media_library', requireAuth, (req, res) => {
  const db = getDb();
  const items = req.user.role_name === 'author'
    ? db.prepare('SELECT * FROM media_library WHERE owner_id = ? OR is_private = 0 ORDER BY created_at DESC').all(req.user.id)
    : db.prepare('SELECT * FROM media_library ORDER BY created_at DESC').all();
  res.json({ items: items.map(serialize) });
});

router.post('/media_library', requireAuth, (req, res, next) => {
  upload.single('file')(req, res, (err) => {
    if (err) {
      if (err.message === 'invalid_mime') {
        return res.status(400).json({ error: 'validation_error', message: 'Invalid file type', fields: ['file'] });
      }
      if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(400).json({ error: 'validation_error', message: 'File too large', fields: ['file'] });
      }
      return next(err);
    }
    if (!req.file) throw validationError('File is required', ['file']);
    const db = getDb();
    const fileName = req.file.filename;
    const altText = (req.body?.altText || '').toString().slice(0, 200);
    const isPrivate = req.body?.isPrivate === 'true' || req.body?.isPrivate === true ? 1 : 0;
    const info = db.prepare(
      'INSERT INTO media_library (file_name, original_name, mime_type, size_bytes, url_path, owner_id, alt_text, is_private) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    ).run(fileName, req.file.originalname, req.file.mimetype, req.file.size, `/uploads/${fileName}`, req.user.id, altText, isPrivate);
    const item = db.prepare('SELECT * FROM media_library WHERE id = ?').get(info.lastInsertRowid);
    res.status(201).json({ item: serialize(item), message: 'uploaded' });
  });
});

router.patch('/media_library/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const item = db.prepare('SELECT * FROM media_library WHERE id = ?').get(id);
  if (!item) throw notFoundError('Media item not found');
  if (item.owner_id !== req.user.id && req.user.role_name !== 'admin' && req.user.role_name !== 'editor') {
    throw forbidError('Cannot modify this media item');
  }
  const updates = [];
  const params = [];
  if (typeof req.body?.altText === 'string') {
    updates.push('alt_text = ?'); params.push(req.body.altText.slice(0, 200));
  }
  if (typeof req.body?.fileName === 'string' && req.body.fileName.trim()) {
    updates.push('file_name = ?'); params.push(req.body.fileName.trim());
  }
  if (typeof req.body?.isPrivate === 'boolean') {
    updates.push('is_private = ?'); params.push(req.body.isPrivate ? 1 : 0);
  }
  if (!updates.length) return res.json({ item: serialize(item) });
  params.push(id);
  db.prepare(`UPDATE media_library SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT * FROM media_library WHERE id = ?').get(id);
  res.json({ item: serialize(updated), message: 'updated' });
});

router.delete('/media_library/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const item = db.prepare('SELECT * FROM media_library WHERE id = ?').get(id);
  if (!item) throw notFoundError('Media item not found');
  if (item.owner_id !== req.user.id && req.user.role_name !== 'admin') {
    throw forbidError('Cannot delete this media item');
  }
  db.prepare('DELETE FROM media_library WHERE id = ?').run(id);
  // Remove file from disk if exists
  const filePath = join(UPLOAD_DIR, basename(item.url_path));
  if (existsSync(filePath)) {
    try { unlinkSync(filePath); } catch { /* ignore */ }
  }
  res.json({ message: 'deleted', id });
});

function serialize(m) {
  return {
    id: m.id,
    fileName: m.file_name,
    originalName: m.original_name,
    mimeType: m.mime_type,
    sizeBytes: m.size_bytes,
    urlPath: m.url_path,
    ownerId: m.owner_id,
    altText: m.alt_text,
    isPrivate: !!m.is_private,
    createdAt: m.created_at
  };
}

export default router;
export { UPLOAD_DIR, MAX_BYTES, ALLOWED_MIME };
