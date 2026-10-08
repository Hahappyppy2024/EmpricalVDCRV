import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Database from 'better-sqlite3';
import { config, ensureDirs } from '../config.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export function openDatabase() {
  ensureDirs();
  const db = new Database(config.dbPath);
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');
  const schema = fs.readFileSync(path.join(__dirname, 'schema.sql'), 'utf8');
  db.exec(schema);
  return db;
}

let db = null;

export function getDb() {
  if (!db) db = openDatabase();
  return db;
}

export function closeDb() {
  if (db) {
    db.close();
    db = null;
  }
}

export function resetDatabase() {
  if (db) {
    db.close();
    db = null;
  }
  if (fs.existsSync(config.dbPath)) {
    fs.rmSync(config.dbPath, { force: true });
    for (const suffix of ['-wal', '-shm']) {
      fs.rmSync(config.dbPath + suffix, { force: true });
    }
  }
  return getDb();
}
