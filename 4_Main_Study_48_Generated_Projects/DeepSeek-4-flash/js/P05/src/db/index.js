import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';
import env from '../config/env.js';
import { SCHEMA } from './schema.js';

export function openDb(dbPath = env.dbPath) {
  fs.mkdirSync(path.dirname(dbPath), { recursive: true });
  const db = new Database(dbPath);
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');
  db.exec(SCHEMA);
  return db;
}

let singleton = null;

export function getDb() {
  if (!singleton) {
    singleton = openDb();
  }
  return singleton;
}

export function closeDb() {
  if (singleton) {
    singleton.close();
    singleton = null;
  }
}
