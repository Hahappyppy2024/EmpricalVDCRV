import Database from 'better-sqlite3';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

const DB_PATH = process.env.DB_PATH || join(__dirname, '..', '..', 'data', 'cms.sqlite');

let _db = null;

export function getDb() {
  if (_db) return _db;
  _db = new Database(DB_PATH);
  _db.pragma('journal_mode = WAL');
  _db.pragma('foreign_keys = ON');
  return _db;
}

export function initSchema() {
  const db = getDb();
  const schemaPath = join(__dirname, 'schema.sql');
  const sql = readFileSync(schemaPath, 'utf8');
  db.exec(sql);
  return db;
}

export function resetDatabase() {
  const db = getDb();
  // Disable foreign keys temporarily so we can drop tables in any order.
  db.pragma('foreign_keys = OFF');
  const tables = [
    'frontend_api_integration_and_errors',
    'import_export',
    'plugin_settings_panel',
    'audit_events',
    'user_and_role_management',
    'navigation_menus',
    'page_templates',
    'comments',
    'public_site',
    'publishing_workflow',
    'rich_text_editor',
    'media_library',
    'content_authoring',
    'categories',
    'account_access',
    'sessions',
    'users',
    'roles'
  ];
  for (const t of tables) {
    db.prepare(`DROP TABLE IF EXISTS ${t}`).run();
  }
  initSchema();
  db.pragma('foreign_keys = ON');
}

export function closeDb() {
  if (_db) {
    _db.close();
    _db = null;
  }
}
