import path from 'node:path';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

dotenv.config();

export const ROOT_DIR = path.resolve(__dirname, '..');

export const config = {
  port: Number(process.env.PORT || 3000),
  host: process.env.HOST || '0.0.0.0',
  publicBaseUrl: process.env.PUBLIC_BASE_URL || 'http://localhost:3000',
  env: process.env.NODE_ENV || 'development',
  sessionTtlMinutes: Number(process.env.SESSION_TTL_MINUTES || 480),
  dbPath: path.resolve(ROOT_DIR, process.env.DB_PATH || 'data/hotel.db'),
  isProduction: (process.env.NODE_ENV || 'development') === 'production',
};
