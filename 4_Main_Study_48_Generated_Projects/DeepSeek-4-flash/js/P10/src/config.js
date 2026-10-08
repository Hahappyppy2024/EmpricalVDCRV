import path from 'node:path';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const ROOT_DIR = path.resolve(__dirname, '..');

dotenv.config({ path: path.join(ROOT_DIR, '.env') });

function int(value, fallback) {
  const n = Number.parseInt(value ?? '', 10);
  return Number.isFinite(n) ? n : fallback;
}

export const config = {
  port: int(process.env.PORT, 3000),
  databasePath: path.resolve(ROOT_DIR, process.env.DATABASE_PATH ?? './data/app.db'),
  uploadDir: path.resolve(ROOT_DIR, process.env.UPLOAD_DIR ?? './data/uploads'),
  maxUploadSizeBytes: int(process.env.MAX_UPLOAD_SIZE_BYTES, 5 * 1024 * 1024),
  allowedUploadMimeTypes: (process.env.ALLOWED_UPLOAD_MIME_TYPES ?? 'text/plain,text/markdown,text/csv,application/json')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean),
  sessionTtlMs: int(process.env.SESSION_TTL_MS, 7 * 24 * 60 * 60 * 1000),
  sessionCookieName: process.env.SESSION_COOKIE_NAME ?? 'aiwebui_sid',
  allowRegistration: (process.env.ALLOW_REGISTRATION ?? 'true') !== 'false',
  defaultProvider: process.env.DEFAULT_PROVIDER ?? 'openai',
  defaultModel: process.env.DEFAULT_MODEL ?? 'gpt-4o-mini',
};
