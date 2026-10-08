import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Database from 'better-sqlite3';
import { config } from '../config.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SCHEMA_PATH = path.join(__dirname, 'schema.sql');

let db = null;

export function initDatabase() {
  if (db) return db;

  fs.mkdirSync(path.dirname(config.dbPath), { recursive: true });

  db = new Database(config.dbPath);
  db.pragma('foreign_keys = ON');
  db.pragma('journal_mode = WAL');

  const schema = fs.readFileSync(SCHEMA_PATH, 'utf8');
  db.exec(schema);
  return db;
}

export function getDb() {
  if (!db) initDatabase();
  return db;
}

export function closeDatabase() {
  if (db) {
    db.close();
    db = null;
  }
}

export function resetDatabase() {
  const dir = path.dirname(config.dbPath);
  fs.mkdirSync(dir, { recursive: true });
  if (fs.existsSync(config.dbPath)) {
    fs.rmSync(config.dbPath, { force: true });
    for (const suffix of ['-wal', '-shm']) {
      if (fs.existsSync(config.dbPath + suffix)) {
        fs.rmSync(config.dbPath + suffix, { force: true });
      }
    }
  }
  return initDatabase();
}
