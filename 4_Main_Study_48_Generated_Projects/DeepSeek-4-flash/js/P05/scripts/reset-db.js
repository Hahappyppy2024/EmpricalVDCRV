import fs from 'node:fs';
import path from 'node:path';
import env from '../src/config/env.js';
import { openDb, closeDb } from '../src/db/index.js';
import { seed } from '../src/db/seed.js';

function removeQuiet(target) {
  if (fs.existsSync(target)) fs.rmSync(target, { recursive: true, force: true });
}

function reset() {
  const dbPath = env.dbPath;
  removeQuiet(dbPath);
  removeQuiet(`${dbPath}-wal`);
  removeQuiet(`${dbPath}-shm`);
  removeQuiet(env.uploadDir);
  removeQuiet(env.exportDir);
  fs.mkdirSync(path.dirname(dbPath), { recursive: true });
  fs.mkdirSync(env.uploadDir, { recursive: true });
  fs.mkdirSync(env.exportDir, { recursive: true });

  const db = openDb(dbPath);
  seed(db);
  closeDb();
  console.log(`[reset-db] Database recreated and seeded at ${dbPath}`);
}

reset();
