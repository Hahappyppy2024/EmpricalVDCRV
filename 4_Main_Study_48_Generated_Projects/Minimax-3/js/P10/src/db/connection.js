import Database from 'better-sqlite3';
import path from 'node:path';
import fs from 'node:fs';
import { config } from '../config.js';

let _db = null;

export function getDb() {
  if (_db) return _db;

  const absPath = path.resolve(process.cwd(), config.dbPath);
  fs.mkdirSync(path.dirname(absPath), { recursive: true });

  _db = new Database(absPath);
  _db.pragma('journal_mode = WAL');
  _db.pragma('foreign_keys = ON');
  _db.pragma('synchronous = NORMAL');
  return _db;
}

export function closeDb() {
  if (_db) {
    _db.close();
    _db = null;
  }
}
