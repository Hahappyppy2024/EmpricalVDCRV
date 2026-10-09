import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import {
  badRequest, conflict, forbidden, notFound, unauthorized
} from '../errors.js';
import {
  requireString, optionalString, requireNumber, optionalNumber
} from '../services/validation.js';
import { recordEvent } from '../services/audit.js';
import { readStoredFile } from '../services/storage.js';

function newId() { return 'col_' + crypto.randomBytes(8).toString('hex'); }
function chunkId() { return 'chk_' + crypto.randomBytes(8).toString('hex'); }
function nowIso() { return new Date().toISOString(); }

const APPROX_CHUNK_SIZE = 600; // characters per chunk

function buildChunks(text) {
  const cleaned = String(text || '').replace(/\r\n/g, '\n').trim();
  if (!cleaned) return [];
  const out = [];
  let i = 0;
  let idx = 0;
  while (i < cleaned.length) {
    const slice = cleaned.slice(i, i + APPROX_CHUNK_SIZE).trim();
    if (slice.length > 0) {
      out.push({ chunk_index: idx++, content: slice });
    }
    i += APPROX_CHUNK_SIZE;
  }
  return out;
}

export function listCollections(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const rows = db.prepare(`
    SELECT id, owner_id, name, description, created_at, updated_at,
           (SELECT COUNT(*) FROM retrieval_collection_files WHERE collection_id = c.id) AS file_count,
           (SELECT COUNT(*) FROM retrieval_chunks WHERE collection_id = c.id) AS chunk_count
    FROM retrieval_collections c
    WHERE owner_id = ?
    ORDER BY updated_at DESC
  `).all(req.user.id);
  res.json({ ok: true, data: { items: rows, total: rows.length } });
}

export function createCollection(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const name = requireString(req.body?.name, 'name', { min: 1, max: 120 });
  const description = optionalString(req.body?.description, 'description', { min: 0, max: 500 }) || null;
  const existing = db.prepare(`SELECT id FROM retrieval_collections WHERE owner_id = ? AND name = ?`)
    .get(req.user.id, name);
  if (existing) throw conflict('Collection with this name already exists');
  const id = newId();
  db.prepare(`INSERT INTO retrieval_collections
    (id, owner_id, name, description, created_at, updated_at)
    VALUES (?,?,?,?,?,?)`).run(id, req.user.id, name, description, nowIso(), nowIso());
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'retrieval_collection.create', targetKind: 'retrieval_collection', targetId: id,
    outcome: 'success' });
  const stored = db.prepare(`SELECT * FROM retrieval_collections WHERE id = ?`).get(id);
  res.status(201).json({ ok: true, data: stored });
}

export function getCollection(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const col = db.prepare(`SELECT * FROM retrieval_collections WHERE id = ?`).get(req.params.id);
  if (!col) throw notFound('Collection not found');
  if (col.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  const files = db.prepare(`
    SELECT f.id, f.filename, f.original_name, f.size_bytes, f.mime_type, f.extension
    FROM retrieval_collection_files rcf
    JOIN stored_files f ON f.id = rcf.file_id
    WHERE rcf.collection_id = ?
    ORDER BY f.created_at DESC
  `).all(col.id);
  const chunkCount = db.prepare(`SELECT COUNT(*) AS c FROM retrieval_chunks WHERE collection_id = ?`)
    .get(col.id).c;
  res.json({ ok: true, data: { ...col, files, chunk_count: chunkCount } });
}

export function updateCollection(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const col = db.prepare(`SELECT * FROM retrieval_collections WHERE id = ?`).get(req.params.id);
  if (!col) throw notFound('Collection not found');
  if (col.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  const updates = [];
  const params = [];
  if (req.body?.name !== undefined) {
    updates.push('name = ?');
    params.push(requireString(req.body.name, 'name', { min: 1, max: 120 }));
  }
  if (req.body?.description !== undefined) {
    updates.push('description = ?');
    params.push(optionalString(req.body.description, 'description', { min: 0, max: 500 }) || null);
  }
  if (updates.length === 0) throw badRequest('Nothing to update');
  updates.push('updated_at = ?'); params.push(nowIso());
  params.push(req.params.id);
  db.prepare(`UPDATE retrieval_collections SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'retrieval_collection.update', targetKind: 'retrieval_collection',
    targetId: req.params.id, outcome: 'success' });
  const refreshed = db.prepare(`SELECT * FROM retrieval_collections WHERE id = ?`).get(req.params.id);
  res.json({ ok: true, data: refreshed });
}

export function deleteCollection(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const col = db.prepare(`SELECT * FROM retrieval_collections WHERE id = ?`).get(req.params.id);
  if (!col) throw notFound('Collection not found');
  if (col.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  db.prepare(`DELETE FROM retrieval_collections WHERE id = ?`).run(req.params.id);
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'retrieval_collection.delete', targetKind: 'retrieval_collection',
    targetId: req.params.id, outcome: 'success' });
  res.json({ ok: true, data: { id: req.params.id, deleted: true } });
}

export function attachFile(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const col = db.prepare(`SELECT * FROM retrieval_collections WHERE id = ?`).get(req.params.id);
  if (!col) throw notFound('Collection not found');
  if (col.owner_id !== req.user.id) throw forbidden();
  const fileId = requireString(req.body?.file_id, 'file_id', { min: 1, max: 64 });
  const file = db.prepare(`SELECT * FROM stored_files WHERE id = ?`).get(fileId);
  if (!file) throw notFound('File not found');
  if (file.owner_id !== req.user.id) throw forbidden('Cannot attach another user\'s file');
  const exists = db.prepare(`SELECT 1 FROM retrieval_collection_files WHERE collection_id = ? AND file_id = ?`)
    .get(col.id, fileId);
  if (exists) throw conflict('File already attached');
  const buf = readStoredFile(file);
  if (!buf) throw badRequest('Stored file content is missing');
  const chunks = buildChunks(buf.toString('utf8'));
  db.transaction(() => {
    db.prepare(`INSERT INTO retrieval_collection_files (collection_id, file_id) VALUES (?, ?)`)
      .run(col.id, fileId);
    db.prepare(`DELETE FROM retrieval_chunks WHERE file_id = ?`).run(fileId);
    const ins = db.prepare(`INSERT INTO retrieval_chunks
      (id, file_id, collection_id, chunk_index, content)
      VALUES (?, ?, ?, ?, ?)`);
    for (const c of chunks) ins.run(chunkId(), fileId, col.id, c.chunk_index, c.content);
    db.prepare(`UPDATE retrieval_collections SET updated_at = ? WHERE id = ?`).run(nowIso(), col.id);
  })();
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'retrieval_collection.attach_file',
    targetKind: 'retrieval_collection', targetId: col.id, outcome: 'success',
    details: { file_id: fileId, chunks: chunks.length } });
  res.status(201).json({
    ok: true, data: { collection_id: col.id, file_id: fileId, chunks_indexed: chunks.length }
  });
}

export function detachFile(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const col = db.prepare(`SELECT * FROM retrieval_collections WHERE id = ?`).get(req.params.id);
  if (!col) throw notFound('Collection not found');
  if (col.owner_id !== req.user.id) throw forbidden();
  const fileId = requireString(req.params.file_id || req.body?.file_id, 'file_id', { min: 1, max: 64 });
  db.transaction(() => {
    db.prepare(`DELETE FROM retrieval_collection_files WHERE collection_id = ? AND file_id = ?`)
      .run(col.id, fileId);
    db.prepare(`DELETE FROM retrieval_chunks WHERE collection_id = ? AND file_id = ?`)
      .run(col.id, fileId);
    db.prepare(`UPDATE retrieval_collections SET updated_at = ? WHERE id = ?`).run(nowIso(), col.id);
  })();
  recordEvent({ actorId: req.user.id, actorRole: req.user.role,
    action: 'retrieval_collection.detach_file',
    targetKind: 'retrieval_collection', targetId: col.id, outcome: 'success',
    details: { file_id: fileId } });
  res.json({ ok: true, data: { collection_id: col.id, file_id: fileId, detached: true } });
}

export function searchCollection(req, res) {
  if (!req.user) throw unauthorized();
  const db = getDb();
  const col = db.prepare(`SELECT * FROM retrieval_collections WHERE id = ?`).get(req.params.id);
  if (!col) throw notFound('Collection not found');
  if (col.owner_id !== req.user.id && req.user.role !== 'admin') throw forbidden();
  const query = requireString(req.body?.query || req.query?.q, 'query', { min: 1, max: 500 });
  const limit = optionalNumber(req.body?.limit || req.query?.limit, 'limit',
    { min: 1, max: 50, integer: true }) || 5;
  const terms = query.toLowerCase().split(/\W+/).filter(t => t.length >= 2);
  const chunks = db.prepare(`
    SELECT id, file_id, chunk_index, content FROM retrieval_chunks WHERE collection_id = ?
  `).all(col.id);
  const scored = chunks.map(c => {
    const lower = c.content.toLowerCase();
    let score = 0;
    for (const t of terms) if (lower.includes(t)) score += 1;
    return { ...c, score };
  }).filter(c => c.score > 0).sort((a, b) => b.score - a.score).slice(0, limit);
  res.json({ ok: true, data: { query, results: scored, total: scored.length } });
}

export function postCollections(req, res) { return createCollection(req, res); }
export function patchCollections(req, res) { return updateCollection(req, res); }
export function getCollections(req, res) { return listCollections(req, res); }
