import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');

function loadDotEnv(file) {
  const envPath = path.join(ROOT, file);
  if (!fs.existsSync(envPath)) return;
  const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);
  for (const raw of lines) {
    const line = raw.trim();
    if (!line || line.startsWith('#')) continue;
    const eq = line.indexOf('=');
    if (eq === -1) continue;
    const key = line.slice(0, eq).trim();
    const value = line.slice(eq + 1).trim().replace(/^["']|["']$/g, '');
    if (process.env[key] === undefined) process.env[key] = value;
  }
}

loadDotEnv('.env');

const env = (key, fallback) => (process.env[key] !== undefined ? process.env[key] : fallback);
const envInt = (key, fallback) => {
  const v = parseInt(env(key, ''), 10);
  return Number.isFinite(v) ? v : fallback;
};

export const config = {
  port: envInt('PORT', 3000),
  host: env('HOST', '0.0.0.0'),
  dbPath: path.resolve(ROOT, env('DB_PATH', './data/app.db')),
  dataDir: path.resolve(ROOT, env('DATA_DIR', './data')),
  uploadDir: path.resolve(ROOT, env('UPLOAD_DIR', './data/uploads')),
  sessionCookieName: env('SESSION_COOKIE_NAME', 'exp_session'),
  sessionTtlHours: envInt('SESSION_TTL_HOURS', 8),
  maxUploadBytes: envInt('MAX_UPLOAD_BYTES', 5242880),
  allowedUploadMimeTypes: env('ALLOWED_UPLOAD_MIME_TYPES', 'image/png,image/jpeg,application/pdf,text/plain')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean),
  wsPath: env('WS_PATH', '/ws'),
  root: ROOT,
  publicDir: path.resolve(ROOT, 'src/public'),
  isProduction: env('NODE_ENV', 'development') === 'production',
};

export function ensureDirs() {
  for (const dir of [config.dataDir, config.uploadDir]) {
    fs.mkdirSync(dir, { recursive: true });
  }
}
