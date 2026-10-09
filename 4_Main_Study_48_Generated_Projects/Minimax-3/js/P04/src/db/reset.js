import fs from 'node:fs';
import path from 'node:path';
import { env } from '../config.js';
import { getDb, runSchema } from './connection.js';

const cfg = env();
const dbPath = path.resolve(process.cwd(), cfg.DATABASE_PATH);
const walPath = dbPath + '-wal';
const shmPath = dbPath + '-shm';

for (const f of [dbPath, walPath, shmPath]) {
  if (fs.existsSync(f)) fs.unlinkSync(f);
}

const db = getDb();
runSchema(db);
db.close();
console.log('[reset] database reset at', dbPath);