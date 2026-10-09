import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function loadDotEnv() {
  const envPath = path.resolve(process.cwd(), '.env');
  if (!fs.existsSync(envPath)) return;
  const text = fs.readFileSync(envPath, 'utf8');
  for (const line of text.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx < 0) continue;
    const key = trimmed.slice(0, idx).trim();
    let value = trimmed.slice(idx + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) ||
        (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    if (!(key in process.env)) process.env[key] = value;
  }
}
loadDotEnv();

function envInt(name, fallback) {
  const v = process.env[name];
  if (v === undefined || v === '') return fallback;
  const n = parseInt(v, 10);
  return Number.isFinite(n) ? n : fallback;
}

function envList(name, fallback) {
  const v = process.env[name];
  if (v === undefined || v === '') return fallback;
  return v.split(',').map(s => s.trim()).filter(Boolean);
}

export const config = {
  port: envInt('PORT', 3000),
  host: process.env.HOST || '127.0.0.1',
  nodeEnv: process.env.NODE_ENV || 'development',
  sessionCookieName: process.env.SESSION_COOKIE_NAME || 'aiassist_sid',
  sessionLifetimeSeconds: envInt('SESSION_LIFETIME_SECONDS', 43200),
  dbPath: process.env.DB_PATH || 'data/app.db',
  uploadDir: process.env.UPLOAD_DIR || 'data/uploads',
  maxUploadBytes: envInt('MAX_UPLOAD_BYTES', 5 * 1024 * 1024),
  allowedUploadExt: envList('ALLOWED_UPLOAD_EXT', [
    '.txt', '.md', '.markdown', '.csv', '.json', '.log'
  ]),
  defaultModelId: process.env.DEFAULT_MODEL_ID || 'local-bench-1',
  defaultTemperature: Number(process.env.DEFAULT_TEMPERATURE ?? '0.2'),
  defaultContextLength: envInt('DEFAULT_CONTEXT_LENGTH', 2048),
  shareAllowedOrigins: envList('SHARE_ALLOWED_ORIGINS', []),
  projectRoot: path.resolve(__dirname, '..', '..')
};
