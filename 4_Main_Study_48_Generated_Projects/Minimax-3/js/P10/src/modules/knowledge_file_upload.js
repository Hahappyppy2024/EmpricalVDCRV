import crypto from 'node:crypto';
import path from 'node:path';
import { getDb } from '../db/connection.js';
import {
  badRequest, forbidden, notFound, unauthorized, uploadFailed
} from '../errors.js';
import { recordEvent } from '../services/audit.js';
import {
  classifyExtension, writeFileBuffer, readStoredFile, removeStoredFile
} from '../services/storage.js';
import { config } from '../config.js';

function newId() { return 'fil_' + crypto.randomBytes(8).toString('hex'); }
function nowIso() { return new Date().toISOString(); }

export function listFiles(req, res) {
  if (!req.user) throw unauthorized();
  const rows = getDb().prepare(`
    SELECT id, owner_id, filename, original_name, mime_type, size_bytes, extension,
           visibility, created_at
    FROM stored_files
    WHERE owner_id = ?
    ORDER BY created_at DESC LIMIT 200
  `).all(req.user.id);
  res.json({ ok: true, data: { items: rows, total: rows.length } });
}

export function uploadFile(req, res) {
  if (!req.user) throw unauthorized();
  if (!req.file) throw uploadFailed('No file provided (multipart field "file")');
  if (req.file.size > config.maxUploadBytes) {
    throw uploadFailed(`File exceeds maximum size of ${config.maxUploadBytes} bytes`);
  }
  const { ext, allowed } = classifyExtension(req.file.originalname);
  if (!allowed) throw uploadFailed(`Extension ${ext || '(none)'} is not allowed`);
  const visibility = req.body?.visibility === 'shared' ? 'shared' : 'private';
  if (visibility === 'shared' && req.user.role !== 'admin') {
    throw forbidden('Only admins may upload shared files');
  }
  const ownerId = req.user.id;
  const { finalName, size, checksum, fullPath } =
    writeFileBuffer(ownerId, req.file.buffer, req.file.originalname);
  const id = newId();
  getDb().prepare(`INSERT INTO stored_files
    (id, owner_id, filename, original_name, mime_type, size_bytes, extension,
     storage_path, checksum, visibility, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)`).run(
      id, ownerId, finalName, req.file.originalname,
      req.file.mimetype || 'application/octet-stream',
      size, ext,
      path.relative(process.cwd(), fullPath).replace(/\\/g, '/'),
      checksum, visibility, nowIso()
    );
  recordEvent({ actorId: ownerId, actorRole: req.user.role,
    action: 'knowledge_file.upload', targetKind: 'stored_file', targetId: id,
    outcome: 'success', details: { size, extension: ext } });
  const stored = getDb().prepare(`
    SELECT id, owner_id, filename, original_name, mime_type, size_bytes, extension,
           visibility, created_at
    FROM stored_files WHERE id = ?
  `).get(id);
  res.status(201).json({ ok: true, data: stored });
}

export function readFile(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const row = db.prepare(`SELECT * FROM stored_files WHERE id = ?`).get(req.params.id);
  if (!row) throw notFound('File not found');
  if (row.owner_id !== req.user.id && req.user.role !== 'admin' && row.visibility !== 'shared') {
    throw forbidden('Cannot access another user\'s private file');
  }
  const buf = readStoredFile(row);
  if (!buf) throw notFound('File contents missing on disk');
  res.setHeader('Content-Type', row.mime_type);
  res.setHeader('Content-Disposition',
    `inline; filename="${encodeURIComponent(row.original_name)}"`);
  res.status(200).send(buf);
}

export function updateFile(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const row = db.prepare(`SELECT * FROM stored_files WHERE id = ?`).get(req.params.id);
  if (!row) throw notFound('File not found');
  if (row.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  const updates = [];
  const params = [];
  if (req.body?.visibility !== undefined) {
    const visibility = req.body.visibility === 'shared' ? 'shared' : 'private';
    if (visibility === 'shared' && req.user.role !== 'admin') {
      throw forbidden('Only admins may mark files as shared');
    }
    updates.push('visibility = ?'); params.push(visibility);
  }
  if (req.body?.original_name !== undefined) {
    updates.push('original_name = ?');
    params.push(String(req.body.original_name).slice(0, 200));
  }
  if (updates.length === 0) throw badRequest('Nothing to update');
  params.push(req.params.id);
  db.prepare(`UPDATE stored_files SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'knowledge_file.update', targetKind: 'stored_file', targetId: req.params.id,
    outcome: 'success' });
  const refreshed = db.prepare(`
    SELECT id, owner_id, filename, original_name, mime_type, size_bytes, extension,
           visibility, created_at
    FROM stored_files WHERE id = ?
  `).get(req.params.id);
  res.json({ ok: true, data: refreshed });
}

export function deleteFile(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const row = db.prepare(`SELECT * FROM stored_files WHERE id = ?`).get(req.params.id);
  if (!row) throw notFound('File not found');
  if (row.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  // Detach from collections first to keep foreign keys clean.
  db.prepare(`DELETE FROM retrieval_collection_files WHERE file_id = ?`).run(row.id);
  db.prepare(`DELETE FROM retrieval_chunks WHERE file_id = ?`).run(row.id);
  db.prepare(`DELETE FROM stored_files WHERE id = ?`).run(row.id);
  removeStoredFile(row);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'knowledge_file.delete', targetKind: 'stored_file', targetId: req.params.id,
    outcome: 'success' });
  res.json({ ok: true, data: { id: req.params.id, deleted: true } });
}

export function postFiles(req, res) { return uploadFile(req, res); }
export function patchFiles(req, res) { return updateFile(req, res); }
export function getFiles(req, res) { return listFiles(req, res); }
