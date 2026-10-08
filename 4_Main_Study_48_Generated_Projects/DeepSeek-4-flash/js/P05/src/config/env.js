import 'dotenv/config';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..', '..');

function toNumber(value, fallback) {
  const n = Number(value);
  return Number.isFinite(n) && n > 0 ? n : fallback;
}

const env = {
  rootDir,
  port: toNumber(process.env.PORT, 3000),
  host: process.env.HOST || '0.0.0.0',
  nodeEnv: process.env.NODE_ENV || 'development',
  dbPath: path.resolve(rootDir, process.env.DB_PATH || './data/cms.db'),
  uploadDir: path.resolve(rootDir, process.env.UPLOAD_DIR || './data/uploads'),
  exportDir: path.resolve(rootDir, process.env.EXPORT_DIR || './data/exports'),
  sessionTtlDays: toNumber(process.env.SESSION_TTL_DAYS, 7),
  maxUploadMb: toNumber(process.env.MAX_UPLOAD_MB, 10),
  publicBaseUrl: process.env.PUBLIC_BASE_URL || `http://localhost:${toNumber(process.env.PORT, 3000)}`
};

export default env;
