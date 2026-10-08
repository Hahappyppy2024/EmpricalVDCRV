import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import multer from 'multer';
import { HttpError } from '../middleware/error.js';
import { config } from '../config.js';

const EXTRACTABLE = new Set([
  'text/plain',
  'text/markdown',
  'text/csv',
  'application/json',
  'text/html',
  'application/xml',
]);

function extractText(mimeType, buffer) {
  if (!EXTRACTABLE.has(mimeType)) {
    return `[Binary document of type ${mimeType}; text extraction not available in local mode.]`;
  }
  const text = buffer.toString('utf8');
  return text.slice(0, 100000);
}

export function createFileService(db) {
  fs.mkdirSync(config.uploadDir, { recursive: true });

  const storage = multer.diskStorage({
    destination: (req, file, cb) => cb(null, config.uploadDir),
    filename: (req, file, cb) => {
      const storedName = `${Date.now()}-${crypto.randomBytes(8).toString('hex')}-${file.originalname.replace(/[^a-zA-Z0-9._-]/g, '_')}`;
      cb(null, storedName);
    },
  });

  const upload = multer({
    storage,
    limits: { fileSize: config.maxUploadSizeBytes },
    fileFilter: (req, file, cb) => {
      const allowed = config.allowedUploadMimeTypes;
      if (allowed.includes('*') || allowed.includes(file.mimetype)) {
        cb(null, true);
      } else {
        cb(new HttpError(415, `File type "${file.mimetype}" is not allowed`, 'UNSUPPORTED_FILE_TYPE'));
      }
    },
  });

  const insertStored = db.prepare(
    `INSERT INTO stored_files (user_id, original_name, stored_name, path, size, mime_type, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'ready', datetime('now'))`
  );
  const insertKnowledge = db.prepare(
    `INSERT INTO knowledge_files (user_id, stored_file_id, description, content, created_at)
     VALUES (?, ?, ?, ?, datetime('now'))`
  );
  const getStored = db.prepare('SELECT * FROM stored_files WHERE id = ?');
  const updateKnowledge = db.prepare('UPDATE knowledge_files SET description = ? WHERE id = ? AND user_id = ?');
  const getKnowledge = db.prepare('SELECT * FROM knowledge_files WHERE id = ?');

  function removePhysicalFile(storedId) {
    const row = getStored.get(storedId);
    if (row && row.path && fs.existsSync(row.path)) {
      try {
        fs.rmSync(row.path, { force: true });
      } catch {
        /* best effort */
      }
    }
  }

  return {
    uploadMiddleware: upload.single('file'),

    async create({ userId, file, description }) {
      if (!file) {
        throw new HttpError(400, 'A file is required', 'FILE_REQUIRED');
      }
      if (file.size <= 0) {
        removePhysicalFile(null);
        throw new HttpError(400, 'Empty files are not allowed', 'EMPTY_FILE');
      }
      if (file.size > config.maxUploadSizeBytes) {
        throw new HttpError(413, 'File exceeds the maximum allowed size', 'FILE_TOO_LARGE');
      }
      let storedId;
      try {
        storedId = insertStored.run(
          userId,
          file.originalname,
          file.filename,
          path.join(config.uploadDir, file.filename),
          file.size,
          file.mimetype || 'application/octet-stream'
        ).lastInsertRowid;
        const content = extractText(file.mimetype || 'application/octet-stream', fs.readFileSync(file.path));
        const knowledgeId = insertKnowledge.run(userId, storedId, description ?? null, content).lastInsertRowid;
        return {
          knowledgeFileId: knowledgeId,
          storedFile: { ...getStored.get(storedId) },
          size: file.size,
        };
      } catch (err) {
        removePhysicalFile(storedId);
        throw err;
      }
    },

    updateDescription({ knowledgeFileId, userId, description }) {
      const row = getKnowledge.get(knowledgeFileId);
      if (!row || row.user_id !== userId) {
        throw new HttpError(404, 'Knowledge file not found', 'NOT_FOUND');
      }
      updateKnowledge.run(description ?? null, knowledgeFileId, userId);
      return getKnowledge.get(knowledgeFileId);
    },

    remove(knowledgeFileId, userId) {
      const row = getKnowledge.get(knowledgeFileId);
      if (!row || row.user_id !== userId) {
        throw new HttpError(404, 'Knowledge file not found', 'NOT_FOUND');
      }
      db.prepare('DELETE FROM knowledge_files WHERE id = ?').run(knowledgeFileId);
      db.prepare('DELETE FROM stored_files WHERE id = ?').run(row.stored_file_id);
      removePhysicalFile(row.stored_file_id);
      return true;
    },
  };
}
