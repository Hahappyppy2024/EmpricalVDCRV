import fs from 'node:fs';
import path from 'node:path';
import env from '../config/env.js';
import { getDb } from '../db/index.js';
import { apiError } from '../lib/http.js';
import { slugify } from '../lib/crypto.js';

export const EXPORT_FORMATS = ['json', 'csv'];

function timestamp() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

export function buildExportPayload(db) {
  const articles = db.prepare(`
    SELECT a.title, a.slug, a.body, a.body_html, a.tags, a.status, a.publish_at, a.published_at,
           u.username AS author_username, c.slug AS category_slug, t.slug AS template_slug
    FROM articles a
    LEFT JOIN users u ON u.id = a.author_id
    LEFT JOIN categories c ON c.id = a.category_id
    LEFT JOIN page_templates t ON t.id = a.template_id
    ORDER BY a.id
  `).all();
  return {
    meta: { format: 'p05-export', version: 1, exportedAt: timestamp() },
    categories: db.prepare('SELECT name, slug, description FROM categories ORDER BY id').all(),
    templates: db.prepare('SELECT name, slug, description, body FROM page_templates ORDER BY id').all(),
    navMenus: db.prepare('SELECT label, url, position FROM nav_menus ORDER BY position, id').all(),
    articles,
    comments: db.prepare('SELECT author_name, author_email, body, status FROM comments ORDER BY id').all(),
    settings: db.prepare('SELECT key, value FROM plugin_settings_panel ORDER BY id').all()
  };
}

export function exportData({ format = 'json', scope = 'full', requestedBy }) {
  const db = getDb();
  if (!EXPORT_FORMATS.includes(format)) {
    throw apiError(400, 'INVALID_FORMAT', `Export format must be one of: ${EXPORT_FORMATS.join(', ')}.`);
  }
  const payload = buildExportPayload(db);
  let filename = '';
  let mimeType = '';
  let content = '';
  let count = 0;

  if (format === 'json') {
    filename = `export-${Date.now()}.json`;
    mimeType = 'application/json';
    content = JSON.stringify(payload, null, 2);
    count = payload.articles.length;
  } else {
    filename = `export-${Date.now()}.csv`;
    mimeType = 'text/csv';
    const header = 'id,title,slug,status,tags,category,author,created_at';
    const rows = payload.articles.map((a, idx) => [
      idx + 1,
      `"${String(a.title).replace(/"/g, '""')}"`,
      `"${String(a.slug).replace(/"/g, '""')}"`,
      a.status,
      `"${(JSON.parse(a.tags || '[]') || []).join('|')}"`,
      a.category_slug || '',
      `"${(a.author_username || '').replace(/"/g, '""')}"`,
      a.published_at || a.publish_at || ''
    ].join(','));
    content = [header, ...rows].join('\n');
    count = payload.articles.length;
  }

  fs.mkdirSync(env.exportDir, { recursive: true });
  const filePath = path.join(env.exportDir, filename);
  fs.writeFileSync(filePath, content);

  const sfId = db.prepare(`INSERT INTO stored_file (original_name, storage_name, mime_type, size_bytes, owner_id, kind, created_at)
    VALUES (@originalName, @storageName, @mimeType, @size, @owner, 'export', @now)`)
    .run({ originalName: filename, storageName: filename, mimeType, size: Buffer.byteLength(content), owner: requestedBy, now: timestamp() }).lastInsertRowid;

  const recordId = db.prepare(`INSERT INTO import_export (kind, format, status, file_id, record_count, scope, requested_by, created_at)
    VALUES ('export', @format, 'completed', @fileId, @count, @scope, @requestedBy, @now)`)
    .run({ format, fileId: sfId, count, scope: String(scope || 'full').slice(0, 80), requestedBy, now: timestamp() }).lastInsertRowid;

  return { id: Number(recordId), filename, mimeType, bytes: Buffer.byteLength(content), recordCount: count, storageName: filename };
}

function safeJson(str) {
  try {
    const value = JSON.parse(str);
    if (!value || typeof value !== 'object') throw new Error('bad payload');
    return value;
  } catch {
    throw apiError(400, 'INVALID_IMPORT_FILE', 'Import file must contain valid JSON in P05 export format.');
  }
}

function getOrCreateCategory(db, cat) {
  if (!cat || !cat.slug) return null;
  const existing = db.prepare('SELECT id FROM categories WHERE slug = ?').get(cat.slug);
  if (existing) return existing.id;
  return db.prepare(`INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, ?)`)
    .run(cat.name || cat.slug, cat.slug, cat.description || null, timestamp()).lastInsertRowid;
}

function getOrCreateTemplate(db, tpl) {
  if (!tpl || !tpl.slug) return null;
  const existing = db.prepare('SELECT id FROM page_templates WHERE slug = ?').get(tpl.slug);
  if (existing) return existing.id;
  return db.prepare(`INSERT INTO page_templates (name, slug, description, body, created_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)`)
    .run(tpl.name || tpl.slug, tpl.slug, tpl.description || null, tpl.body || '', tpl.created_by || 1, timestamp(), timestamp()).lastInsertRowid;
}

export function importData({ filePath, originalName, requestedBy }) {
  const db = getDb();
  if (!filePath) {
    throw apiError(400, 'IMPORT_FILE_REQUIRED', 'An import file is required.');
  }
  const raw = fs.readFileSync(filePath, 'utf8');
  const payload = safeJson(raw);

  const summary = { categoriesCreated: 0, templatesCreated: 0, articlesCreated: 0, articlesSkipped: 0, commentsImported: 0, settingsImported: 0, navMenusCreated: 0 };

  const tx = db.transaction(() => {
    for (const cat of payload.categories || []) {
      if (getOrCreateCategory(db, cat)) summary.categoriesCreated += 1;
    }
    for (const tpl of payload.templates || []) {
      if (getOrCreateTemplate(db, tpl)) summary.templatesCreated += 1;
    }
    for (const menu of payload.navMenus || []) {
      const existing = db.prepare('SELECT id FROM nav_menus WHERE url = ? AND label = ?').get(menu.url, menu.label);
      if (!existing) {
        db.prepare(`INSERT INTO nav_menus (label, url, position, created_at) VALUES (?, ?, ?, ?)`)
          .run(menu.label, menu.url, menu.position || 0, timestamp());
        summary.navMenusCreated += 1;
      }
    }
    for (const a of payload.articles || []) {
      const slug = a.slug || slugify(a.title);
      if (db.prepare('SELECT id FROM articles WHERE slug = ?').get(slug)) {
        summary.articlesSkipped += 1;
        continue;
      }
      const authorRow = a.author_username ? db.prepare('SELECT id FROM users WHERE username = ?').get(a.author_username) : null;
      const categoryId = a.category_slug ? db.prepare('SELECT id FROM categories WHERE slug = ?').get(a.category_slug) : null;
      const templateRow = a.template_slug ? db.prepare('SELECT id FROM page_templates WHERE slug = ?').get(a.template_slug) : null;
      const status = ['draft', 'review', 'scheduled', 'published'].includes(a.status) ? a.status : 'draft';
      const now = timestamp();
      const articleId = db.prepare(`INSERT INTO articles (title, slug, body, body_html, tags, status, author_id, category_id, template_id, publish_at, published_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
        .run(
          a.title || 'Untitled',
          slug,
          a.body || '',
          a.body_html || '',
          typeof a.tags === 'string' ? a.tags : JSON.stringify(a.tags || []),
          status,
          (authorRow && authorRow.id) || requestedBy,
          (categoryId && categoryId.id) || null,
          (templateRow && templateRow.id) || null,
          a.publish_at || null,
          a.published_at || null,
          now,
          now
        ).lastInsertRowid;
      db.prepare(`INSERT INTO rich_text_editor (article_id, content, content_html, version, updated_by, created_at, updated_at)
        VALUES (?, ?, ?, 1, ?, ?, ?)`)
        .run(articleId, a.body || '', a.body_html || '', requestedBy, now, now);
      summary.articlesCreated += 1;
    }
    for (const c of payload.comments || []) {
      const articleRow = db.prepare('SELECT id FROM articles WHERE slug = ?').get(c.slug);
      const articleId = c.article_id || (articleRow && articleRow.id);
      if (!articleId) continue;
      db.prepare(`INSERT INTO comments (article_id, author_name, author_email, body, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?)`)
        .run(articleId, c.author_name || 'Imported', c.author_email || null, c.body || '', ['pending', 'approved', 'removed'].includes(c.status) ? c.status : 'pending', timestamp());
      summary.commentsImported += 1;
    }
    for (const s of payload.settings || []) {
      if (!s.key) continue;
      const existing = db.prepare('SELECT id FROM plugin_settings_panel WHERE key = ?').get(s.key);
      if (existing) {
        db.prepare('UPDATE plugin_settings_panel SET value = ?, updated_by = ?, updated_at = ? WHERE id = ?').run(String(s.value), requestedBy, timestamp(), existing.id);
      } else {
        db.prepare(`INSERT INTO plugin_settings_panel (key, value, description, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)`)
          .run(s.key, String(s.value ?? ''), null, requestedBy, timestamp());
      }
      summary.settingsImported += 1;
    }
  });
  tx();

  // copy imported file into stored_file for traceability
  const size = fs.statSync(filePath).size;
  const storageName = path.basename(filePath);
  const sfId = db.prepare(`INSERT INTO stored_file (original_name, storage_name, mime_type, size_bytes, owner_id, kind, created_at)
    VALUES (@originalName, @storageName, 'application/json', @size, @owner, 'import', @now)`)
    .run({ originalName, storageName, size, owner: requestedBy, now: timestamp() }).lastInsertRowid;

  const recordId = db.prepare(`INSERT INTO import_export (kind, format, status, file_id, record_count, scope, requested_by, created_at)
    VALUES ('import', 'json', 'completed', @fileId, @count, 'import', @requestedBy, @now)`)
    .run({ fileId: sfId, count: summary.articlesCreated, requestedBy, now: timestamp() }).lastInsertRowid;

  const totalRecords = payload.articles ? payload.articles.length : 0;
  return { id: Number(recordId), summary, totalRecords };
}
