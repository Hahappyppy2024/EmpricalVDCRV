import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { env } from '../config.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

let _db = null;

export function getDb() {
  if (_db) return _db;
  const cfg = env();
  const dbPath = path.resolve(process.cwd(), cfg.DATABASE_PATH);
  fs.mkdirSync(path.dirname(dbPath), { recursive: true });
  _db = new Database(dbPath);
  _db.pragma('journal_mode = WAL');
  _db.pragma('foreign_keys = ON');
  return _db;
}

export function closeDb() {
  if (_db) {
    _db.close();
    _db = null;
  }
}

export function schemaPath() {
  return path.resolve(__dirname, 'schema.sql');
}

export function runSchema(db) {
  const sql = fs.readFileSync(schemaPath(), 'utf8');
  db.exec(sql);
}