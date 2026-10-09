import crypto from 'node:crypto';
import path from 'node:path';
import fs from 'node:fs';
import { config } from '../config.js';

const ROOT = path.resolve(process.cwd(), config.uploadDir);

export function ensureUploadRoot() {
  fs.mkdirSync(ROOT, { recursive: true });
}

export function classifyExtension(name) {
  const ext = path.extname(name || '').toLowerCase();
  return { ext, allowed: config.allowedUploadExt.includes(ext) };
}

export function safeStoredFilename(originalName) {
  const ext = path.extname(originalName || '').toLowerCase();
  const base = path.basename(originalName || 'file', ext)
    .replace(/[^a-zA-Z0-9_-]+/g, '_').slice(0, 60) || 'file';
  const stamp = crypto.randomBytes(8).toString('hex');
  return `${Date.now()}_${stamp}_${base}${ext}`;
}

export function writeFileBuffer(ownerId, buffer, filename) {
  ensureUploadRoot();
  const ownerDir = path.join(ROOT, ownerId);
  fs.mkdirSync(ownerDir, { recursive: true });
  const finalName = safeStoredFilename(filename);
  const fullPath = path.join(ownerDir, finalName);
  fs.writeFileSync(fullPath, buffer);
  const checksum = crypto.createHash('sha256').update(buffer).digest('hex');
  return { fullPath, finalName, size: buffer.length, checksum };
}

export function readStoredFile(fileRow) {
  if (!fileRow) return null;
  if (fileRow.storage_path && fileRow.storage_path.startsWith('seed://')) {
    // Deterministic synthetic content for seeded files.
    return Buffer.from(generateSeedContent(fileRow.storage_path));
  }
  const fullPath = path.join(ROOT, fileRow.owner_id, fileRow.filename);
  if (!fs.existsSync(fullPath)) return null;
  return fs.readFileSync(fullPath);
}

export function removeStoredFile(fileRow) {
  if (!fileRow) return;
  if (fileRow.storage_path && fileRow.storage_path.startsWith('seed://')) return;
  const fullPath = path.join(ROOT, fileRow.owner_id, fileRow.filename);
  if (fs.existsSync(fullPath)) fs.unlinkSync(fullPath);
}

function generateSeedContent(seedPath) {
  switch (seedPath) {
    case 'seed://rag-notes.txt':
      return [
        'Retrieval augmented generation pairs an external retriever with a generator.',
        'Vector stores index documents by embedding similarity for fast lookup.',
        'Citations indicate which documents informed an answer.'
      ].join('\n\n');
    case 'seed://meeting.md':
      return [
        '# Meeting summary',
        '',
        '- Discussed onboarding flow.',
        '- Decided to ship retrieval collection UI in the next sprint.',
        '- Action item: review API key rotation UX.'
      ].join('\n');
    default:
      return '';
  }
}
