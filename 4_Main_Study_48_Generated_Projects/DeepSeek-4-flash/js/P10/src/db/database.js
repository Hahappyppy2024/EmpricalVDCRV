import fs from 'node:fs';
import path from 'node:path';
import Database from 'better-sqlite3';
import { config, ROOT_DIR } from '../config.js';

const SCHEMA_PATH = path.join(ROOT_DIR, 'src', 'db', 'schema.sql');

function ensureDataDirs() {
  for (const dir of [path.dirname(config.databasePath), config.uploadDir]) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

export function openDatabase() {
  ensureDataDirs();
  const db = new Database(config.databasePath);
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');
  const schema = fs.readFileSync(SCHEMA_PATH, 'utf8');
  db.exec(schema);
  return db;
}

export function closeDatabase(db) {
  if (db) {
    try {
      db.close();
    } catch {
      /* already closed */
    }
  }
}

export function nowIso() {
  return new Date().toISOString();
}
